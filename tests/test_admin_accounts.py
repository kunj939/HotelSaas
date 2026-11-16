"""Limited-admin authorization and attribution tests. Disposable MySQL site only."""
import os
import unittest
import uuid
from test_menu_api import Client


@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'), 'Disposable test site required')
class AdminAccountsTest(unittest.TestCase):
    def test_onboarding_permissions_counts_and_revocation(self):
        base = os.environ['TABLEBLOOM_TEST_URL']
        root = Client(base)
        root.login(os.environ['TABLEBLOOM_TEST_EMAIL'], os.environ['TABLEBLOOM_TEST_PASSWORD'])
        key = uuid.uuid4().hex[:12]
        password = uuid.uuid4().hex
        accounts, restaurants = [], []
        try:
            self.assertEqual(Client(base).request('admin/team/create', {})[0], 401)
            for suffix in ['a', 'b']:
                username = f'agent.{key}.{suffix}'
                created = root.call('admin/team/create', dict(full_name='Test Admin '+suffix,
                    username=username, password=password, role='super'))
                accounts.append(created)
            a, b = Client(base), Client(base)
            a.login(accounts[0]['username'].upper(), password)
            b.login(accounts[1]['username'], password)
            self.assertEqual(a.call('bootstrap')['actor']['role'], 'admin')
            self.assertIsNone(a.call('bootstrap')['actor']['restaurant_id'])
            self.assertNotIn('password_hash', a.call('bootstrap')['actor'])
            self.assertEqual(int(a.call('agent/overview')['stats']['total']), 0)
            for route in ['admin/team','admin/overview','admin/restaurants','admin/pricing',
                          'admin/settings','admin/upi-payments','owner/settings','owner/outlets',
                          'owner/orders','owner/menu','owner/billing']:
                self.assertEqual(a.request(route)[0], 401, route)
            for route in ['admin/team/create','admin/team/access','admin/team/reset-password',
                          'admin/pricing/save','admin/restaurants/update','admin/restaurants/create',
                          'admin/restaurants/purge','admin/restaurants/link','owner/menu/save',
                          'owner/outlets/create','owner/switch','owner/settings/save']:
                self.assertEqual(a.request(route, {})[0], 401, route)
            self.assertEqual(root.request('agent/overview')[0], 401)
            root_id = root.call('bootstrap')['actor']['id']
            self.assertEqual(root.request('admin/team/access', dict(id=root_id,active=False))[0], 404)
            self.assertEqual(root.request('admin/team/reset-password', dict(id=root_id,password=password))[0], 404)
            self.assertEqual(root.request('admin/team/create', dict(full_name='Duplicate',
                username=accounts[0]['username'],password=password))[0], 400)

            def payload(suffix):
                return dict(name='Referral Restaurant '+suffix, slug=f'referral-{key}-{suffix}',
                    email=f'referral-{key}-{suffix}@example.test',password=password,
                    owner_name='Restaurant Owner', qr_mode='counter',menu_template='none')

            data = payload('a')
            csrf = a.csrf
            a.csrf = 'expired'
            self.assertEqual(a.request('agent/restaurants/create', data)[0], 419)
            a.csrf = csrf
            for field, value in dict(monthly_fee=1,setup_fee_paid=1,status='active',
                    subscription_ends='2099-01-01',role='super',admin_user_id=accounts[1]['id']).items():
                self.assertEqual(a.request('agent/restaurants/create', dict(data, **{field:value}))[0], 403)
            self.assertEqual(int(a.call('agent/overview')['stats']['total']), 0)
            for client, suffix in [(a,'a'),(a,'a2'),(b,'b')]:
                data = payload(suffix)
                created = client.call('agent/restaurants/create', data)
                restaurants.append((created['id'], data['slug']))
            own = a.call('agent/overview', query={'admin_user_id':accounts[1]['id'],'page':999})
            self.assertEqual(int(own['stats']['total']), 2)
            self.assertEqual(int(own['stats']['this_month']), 2)
            self.assertEqual(own['page'], 1)
            self.assertEqual({r['slug'] for r in own['restaurants']}, {payload('a')['slug'],payload('a2')['slug']})
            self.assertEqual(int(b.call('agent/overview')['stats']['total']), 1)
            for restaurant in own['restaurants']:
                self.assertEqual(set(restaurant), {'name','slug','created_at','status'})
            duplicate = dict(payload('a'), slug=f'rollback-{key}')
            self.assertEqual(a.request('agent/restaurants/create', duplicate)[0], 400)
            self.assertEqual(int(a.call('agent/overview')['stats']['total']), 2)
            self.assertEqual(Client(base).request('public/restaurant',query={'slug':duplicate['slug']})[0],404)
            owner = Client(base)
            owner.login(payload('a')['email'], password)
            settings = owner.call('owner/settings')['restaurant']
            self.assertEqual(float(settings['monthly_fee']),float(root.call('admin/pricing')['default_monthly_fee']))
            self.assertEqual(settings['status'],'trial')
            self.assertEqual(int(settings['setup_fee_paid']),0)
            self.assertEqual(len(owner.call('owner/outlets')['actor']['outlets']),1)
            self.assertEqual(owner.request('agent/overview')[0],401)
            self.assertEqual(owner.request('admin/team')[0],401)
            rows = root.call('admin/team')['admins']
            self.assertEqual(int(next(row for row in rows if row['id']==accounts[0]['id'])['restaurants_added']),2)
            self.assertNotIn('password_hash',str(rows))

            # Permanent removal preserves the referral record and historical count.
            rid, slug = restaurants.pop(1)
            root.call('admin/restaurants/delete',dict(id=rid,confirm_slug=slug))
            root.call('admin/restaurants/purge',dict(id=rid,confirm_slug=slug))
            own = a.call('agent/overview')
            self.assertEqual(int(own['stats']['total']),2)
            self.assertEqual(next(r for r in own['restaurants'] if r['slug']==slug)['status'],'removed')

            root.call('admin/team/access',dict(id=accounts[0]['id'],active=False))
            self.assertEqual(a.request('agent/overview')[0],401)
            root.call('admin/team/access',dict(id=accounts[0]['id'],active=True))
            self.assertEqual(a.request('agent/overview')[0],401,'Re-enabling must not revive old sessions')
            a.login(accounts[0]['username'],password)
            changed = uuid.uuid4().hex
            root.call('admin/team/reset-password',dict(id=accounts[0]['id'],password=changed))
            self.assertEqual(a.request('agent/overview')[0],401)
            self.assertEqual(Client(base).request('auth/login',dict(email=accounts[0]['username'],password=password))[0],401)
            a.login(accounts[0]['username'],changed)
            other = Client(base)
            other.login(accounts[0]['username'],changed)
            a.call('agent/password',dict(current_password=changed,new_password=password))
            self.assertEqual(a.request('agent/overview')[0],200)
            self.assertEqual(other.request('agent/overview')[0],401)
            print('PASS: admin usernames, isolated counts, fixed pricing, server permission denials, rollback, durable attribution, session revocation')
        finally:
            for rid, slug in restaurants:
                root.call('admin/restaurants/delete',dict(id=rid,confirm_slug=slug))
                root.call('admin/restaurants/purge',dict(id=rid,confirm_slug=slug))
            for account in accounts:
                root.call('admin/team/access',dict(id=account['id'],active=False))


if __name__ == '__main__':
    unittest.main()
