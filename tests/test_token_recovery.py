"""Sessionless public receipt recovery, date/tenant isolation and privacy. Disposable site only."""
import datetime
import os
import subprocess
import unittest
import uuid
from test_menu_api import Client


@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'), 'Disposable test site required')
class TokenRecoveryTest(unittest.TestCase):
    def test_recovery_without_original_session(self):
        base = os.environ['TABLEBLOOM_TEST_URL']
        root = Client(base)
        root.login(os.environ['TABLEBLOOM_TEST_EMAIL'],os.environ['TABLEBLOOM_TEST_PASSWORD'])
        key, password = uuid.uuid4().hex[:12], uuid.uuid4().hex
        restaurants = []
        try:
            placed, owners = [], []
            for suffix in ['a','b']:
                slug = f'token-{key}-{suffix}'
                email = slug+'@example.test'
                created = root.call('admin/restaurants/create',dict(name='Token Kitchen '+suffix,
                    slug=slug,email=email,password=password,qr_mode='counter'))
                restaurants.append((created['id'],slug))
                owner = Client(base)
                owner.login(email,password)
                owners.append(owner)
                category = owner.call('owner/menu')['categories'][0]['id']
                item = owner.call('owner/menu/save',dict(name='House pizza '+suffix,category_id=category,
                    base_price=120,available=1,options=[],addons=[]))
                guest = Client(base)
                data = dict(slug=slug,client_request_id=str(uuid.uuid4()),customer_name='Private Guest',
                    customer_phone='9876543210',note='Private kitchen note',items=[dict(item_id=item['id'],quantity=2)])
                order = guest.call('public/order',data)['order']
                self.assertEqual(int(order['token_no']),1)
                self.assertRegex(order['token_day'],r'^\d{4}-\d{2}-\d{2}$')
                self.assertEqual(guest.call('public/order',data)['order']['token_day'],order['token_day'])
                placed.append(order)
                if suffix=='a': first_payload = data
            fresh = Client(base)
            for index, (rid,slug) in enumerate(restaurants):
                result = fresh.call('public/token',query=dict(slug=slug,token=1))['order']
                self.assertEqual(result['id'],placed[index]['id'])
                self.assertEqual(result['items'][0]['dish_name'],'House pizza '+('a' if index==0 else 'b'))
                self.assertEqual(float(result['total']),240)
                self.assertEqual(set(result),{'id','token_no','token_day','table_no','status','call_count','called_at','total','created_at','updated_at','items'})
                for secret in ['Private Guest','9876543210','Private kitchen note',placed[index]['tracking_key'],first_payload['client_request_id']]:
                    self.assertNotIn(secret,str(result))
            slug = restaurants[0][1]
            owners[0].call('owner/order/status',dict(id=placed[0]['id'],status='accepted'))
            self.assertEqual(fresh.call('public/token',query=dict(slug=slug,token=1))['order']['status'],'accepted')
            for token in ['0','-1','1 OR 1=1','1.5','4294967296','abc','']:
                self.assertEqual(fresh.request('public/token',query=dict(slug=slug,token=token))[0],400)
            for day in ['2026-02-30','tomorrow','2999-01-01','2026-1-1']:
                self.assertEqual(fresh.request('public/token',query=dict(slug=slug,token=1,day=day))[0],400)
            self.assertEqual(fresh.request('public/token',query=dict(slug='missing-'+key,token=1))[0],404)
            self.assertEqual(fresh.request('public/token',query=dict(slug=slug,token=99999))[0],404)
            self.assertEqual(fresh.request('public/token',dict(slug=slug,token=1))[0],405)
            self.assertEqual(fresh.request('public/status',query=dict(id=placed[0]['id'],key='1'))[0],403)
            self.assertEqual(fresh.call('public/status',query=dict(id=placed[0]['id'],key=placed[0]['tracking_key']))['order']['id'],placed[0]['id'])
            if os.getenv('TABLEBLOOM_PHP') and os.getenv('TABLEBLOOM_TEST_CONFIG'):
                # Two dates with the same token must recover different receipts.
                first_payload['client_request_id']=str(uuid.uuid4())
                second = fresh.call('public/order',first_payload)['order']
                php = '''$c=require getenv('TABLEBLOOM_TEST_CONFIG');require 'public/api/db.php';
                execq('UPDATE orders SET token_day=DATE_SUB(CURDATE(),INTERVAL 1 DAY),created_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=?',[(int)$argv[1]]);
                execq('UPDATE orders SET token_no=1 WHERE id=?',[(int)$argv[2]]);'''
                subprocess.run([os.environ['TABLEBLOOM_PHP'],'-r',php,str(placed[0]['id']),str(second['id'])],check=True)
                yesterday=(datetime.date.fromisoformat(placed[0]['token_day'])-datetime.timedelta(days=1)).isoformat()
                self.assertEqual(fresh.call('public/token',query=dict(slug=slug,token=1))['order']['id'],second['id'])
                self.assertEqual(fresh.call('public/token',query=dict(slug=slug,token=1,day=yesterday))['order']['id'],placed[0]['id'])
            print('PASS: token receipt without old session, public-only fields, restaurant/date isolation, validation, live status, private-key tracking')
        finally:
            for rid,slug in restaurants:
                root.call('admin/restaurants/delete',dict(id=rid,confirm_slug=slug))
                root.call('admin/restaurants/purge',dict(id=rid,confirm_slug=slug))


if __name__ == '__main__':
    unittest.main()
