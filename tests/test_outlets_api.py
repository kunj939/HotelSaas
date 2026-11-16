"""Verify restaurant selection and access isolation on a disposable test site."""
import os
import unittest
import uuid
from test_menu_api import Client


@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'), 'Disposable test site required')
class OutletSelectionTest(unittest.TestCase):
    def test_owner_creates_outlets_and_copies_menu_independently(self):
        base = os.environ['TABLEBLOOM_TEST_URL']
        admin = Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'], os.environ['TABLEBLOOM_TEST_PASSWORD'])
        key = uuid.uuid4().hex[:12]
        password = uuid.uuid4().hex
        created = []
        try:
            def account(suffix):
                slug = f'self-outlet-{key}-{suffix}'
                email = slug+'@example.test'
                r = admin.call('admin/restaurants/create', dict(name='Outlet '+suffix, slug=slug, email=email, password=password))
                created.append((r['id'], slug))
                client = Client(base)
                client.login(email, password)
                return r['id'], client, email
            home, owner, email = account('home')
            foreign, other, other_email = account('other')
            cat = owner.call('owner/menu')['categories'][0]
            owner.call('owner/menu/save', dict(name='House pizza', category_id=cat['id'], base_price=199,
                food_type='veg', options=[dict(name='Large', price=299)], addons=[], available=1))
            price = owner.call('owner/outlets')['pricing']
            payload = dict(name='City Centre', slug=f'self-outlet-{key}-city', address='City Centre, Gurugram',
                qr_mode='table', table_count=6, copy_menu_from=home, monthly_fee=1, setup_fee_paid=1)
            before = len(owner.call('owner/outlets')['actor']['outlets'])
            initial_bill = owner.call('owner/billing')['quotes']['monthly']
            self.assertEqual(float(price['next_outlet_fee']), 0)
            self.assertEqual(float(price['extra_outlet_fee']), 250)
            self.assertEqual(Client(base).request('owner/outlets/create', payload)[0], 401)
            token = owner.csrf
            owner.csrf = 'invalid'
            self.assertEqual(owner.request('owner/outlets/create', payload)[0], 419)
            owner.csrf = token
            self.assertEqual(owner.request('owner/outlets/create', dict(payload, copy_menu_from=foreign))[0], 403)
            for invalid in [dict(slug='bad/link'), dict(slug='a'*91), dict(qr_mode='bad'), dict(table_count=0), dict(name='')]:
                self.assertEqual(owner.request('owner/outlets/create', dict(payload, **invalid))[0], 400)
            result = owner.call('owner/outlets/create', payload)
            created.append((result['id'], payload['slug']))
            self.assertEqual(result['copied_items'], 1)
            self.assertEqual(len(result['actor']['outlets']), before+1)
            listing = next(r for r in admin.call('admin/restaurants')['restaurants'] if int(r['id']) == int(result['id']))
            self.assertEqual(listing['owner_email'], email)
            self.assertEqual(owner.request('owner/outlets/create', payload)[0], 400)
            self.assertEqual(len(owner.call('owner/outlets')['actor']['outlets']), before+1)
            self.assertEqual(other.request('owner/switch', dict(restaurant_id=result['id']))[0], 403)
            admin.call('admin/restaurants/link', dict(restaurant_id=result['id'], email=other_email))
            other.call('owner/switch', dict(restaurant_id=result['id']))
            self.assertEqual(other.request('owner/billing')[0], 403)
            self.assertEqual(other.request('owner/billing/upi', dict(plan='monthly', utr='123456789123'))[0], 403)
            self.assertEqual(int(owner.call('owner/billing')['restaurant']['id']), int(home))
            owner.call('owner/switch', dict(restaurant_id=result['id']))
            settings = owner.call('owner/settings')['restaurant']
            self.assertEqual(settings['address'], payload['address'])
            self.assertEqual(float(settings['monthly_fee']), 0)
            self.assertEqual(int(settings['setup_fee_paid']), 1)
            self.assertTrue(settings['billing_shared'])
            shared_bill = owner.call('owner/billing')
            self.assertEqual(int(shared_bill['restaurant']['id']), int(home))
            self.assertEqual(shared_bill['quotes']['monthly'], initial_bill)
            self.assertEqual(float(owner.call('owner/outlets')['pricing']['next_outlet_fee']), 250)
            copied = owner.call('owner/menu')['items'][0]
            self.assertEqual(copied['name'], 'House pizza')
            self.assertEqual(copied['options'], [dict(name='Large', price=299)])
            copied.update(name='City pizza', base_price=249)
            owner.call('owner/menu/save', copied)
            owner.call('owner/switch', dict(restaurant_id=home))
            self.assertEqual(owner.call('owner/menu')['items'][0]['name'], 'House pizza')
            empty = owner.call('owner/outlets/create', dict(name='Counter', slug=f'self-outlet-{key}-counter', qr_mode='counter'))
            created.append((empty['id'], empty['slug']))
            billing = owner.call('owner/billing')['quotes']['monthly']
            self.assertEqual(int(billing['extra_outlets']), 1)
            self.assertEqual(float(billing['extra_fee']), float(price['extra_outlet_fee']))
            self.assertEqual(float(billing['total']), float(initial_bill['total'])+250)
            owner.call('owner/switch', dict(restaurant_id=empty['id']))
            self.assertEqual(owner.call('owner/menu')['items'], [])
            self.assertEqual(len(owner.call('owner/menu')['categories']), 5)
            self.assertEqual(owner.call('owner/billing')['quotes']['monthly'], billing)
            home_row = next(r for r in admin.call('admin/restaurants')['restaurants'] if int(r['id']) == int(home))
            admin.call('admin/restaurants/update', dict(id=home, status='active', subscription_ends='2000-01-01', monthly_fee=home_row['monthly_fee'], setup_fee_paid=1))
            public = Client(base)
            self.assertFalse(public.call('public/restaurant', query={'slug':payload['slug']})['restaurant']['can_order'])
            admin.call('admin/restaurants/update', dict(id=home, status='active', subscription_ends=home_row['subscription_ends'], monthly_fee=home_row['monthly_fee'], setup_fee_paid=1))
            self.assertTrue(public.call('public/restaurant', query={'slug':payload['slug']})['restaurant']['can_order'])
            self.assertEqual(owner.call('owner/settings')['restaurant']['subscription_ends'], home_row['subscription_ends'])
            self.assertEqual(float(owner.call('owner/billing')['quotes']['monthly']['setup_fee']), 0)
            admin.call('admin/restaurants/stop', dict(id=empty['id'], stop=True))
            owner.call('owner/switch', dict(restaurant_id=home))
            self.assertEqual(float(owner.call('owner/billing')['quotes']['monthly']['extra_fee']), 0)
            admin.call('admin/restaurants/stop', dict(id=empty['id'], stop=False))
            self.assertEqual(float(owner.call('owner/billing')['quotes']['monthly']['extra_fee']), 250)
            new_password = uuid.uuid4().hex
            admin.call('admin/password', dict(restaurant_id=result['id'], password=new_password))
            owner.login(email, new_password)
            self.assertEqual(len(owner.call('owner/outlets')['actor']['outlets']), 3)
        finally:
            for rid, slug in reversed(created):
                admin.call('admin/restaurants/delete', dict(id=rid))
                admin.call('admin/restaurants/purge', dict(id=rid, confirm_slug=slug))

    def test_link_switch_revoke_and_isolate(self):
        base = os.environ['TABLEBLOOM_TEST_URL']
        admin = Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'], os.environ['TABLEBLOOM_TEST_PASSWORD'])
        token = uuid.uuid4().hex[:12]
        password = uuid.uuid4().hex
        accounts = []
        for suffix in ['a', 'b', 'unlinked']:
            email = f'outlet-{token}-{suffix}@example.test'
            slug = f'outlet-{token}-{suffix}'
            admin.call('admin/restaurants/create', dict(name='Outlet '+suffix, slug=slug, email=email, password=password))
            owner = Client(base)
            owner.login(email, password)
            info = owner.call('owner/outlets')['actor']
            rid = int(info['active_restaurant_id'])
            category = owner.call('owner/menu')['categories'][0]
            owner.call('owner/menu/save', dict(name='Dish '+suffix, category_id=category['id'], base_price=100,
                                              food_type='veg', options=[], addons=[], available=1))
            accounts.append(dict(id=rid, email=email, client=owner, actor=info))
        a, b, other = accounts
        owner = a['client']
        self.assertEqual([int(o['id']) for o in owner.call('owner/outlets')['actor']['outlets']], [a['id']])
        settings = b['client'].call('owner/settings')['restaurant']
        settings.update(address='DLF Phase 3, Gurugram', qr_mode='counter')
        b['client'].call('owner/settings/save', settings)
        # A fresh list picks up an admin link without requiring the owner to sign in again.
        admin.call('admin/restaurants/link', dict(restaurant_id=b['id'], email=a['email']))
        actor = owner.call('owner/outlets')['actor']
        self.assertEqual({int(o['id']) for o in actor['outlets']}, {a['id'], b['id']})
        outlet = next(o for o in actor['outlets'] if int(o['id']) == b['id'])
        self.assertEqual(outlet['address'], settings['address'])
        self.assertEqual(outlet['qr_mode'], 'counter')
        switched = owner.call('owner/switch', dict(restaurant_id=b['id']))['actor']
        self.assertEqual(int(switched['active_restaurant_id']), b['id'])
        self.assertEqual(int(owner.call('bootstrap')['actor']['active_restaurant_id']), b['id'])
        self.assertEqual(int(owner.call('owner/overview')['restaurant']['id']), b['id'])
        self.assertEqual([i['name'] for i in owner.call('owner/menu')['items']], ['Dish b'])
        self.assertEqual(owner.request('owner/switch', dict(restaurant_id=other['id']))[0], 403)
        self.assertEqual(owner.request('owner/switch', dict(restaurant_id=0))[0], 403)
        self.assertEqual(int(owner.call('owner/outlets')['actor']['active_restaurant_id']), b['id'])
        foreign = other['client'].call('owner/menu')['items'][0]
        self.assertEqual(owner.request('owner/menu/save', foreign)[0], 400)
        self.assertEqual(other['client'].call('owner/menu')['items'][0]['name'], 'Dish unlinked')
        # Removing access while B is selected falls back to A, and B cannot be selected again.
        admin.call('admin/restaurants/unlink', dict(restaurant_id=b['id'], user_id=a['actor']['id']))
        actor = owner.call('owner/outlets')['actor']
        self.assertEqual(int(actor['active_restaurant_id']), a['id'])
        self.assertEqual([int(o['id']) for o in actor['outlets']], [a['id']])
        self.assertEqual(owner.request('owner/switch', dict(restaurant_id=b['id']))[0], 403)
        self.assertEqual([i['name'] for i in owner.call('owner/menu')['items']], ['Dish a'])
        admin.call('admin/restaurants/link', dict(restaurant_id=b['id'], email=a['email']))
        admin.call('admin/restaurants/stop', dict(id=b['id'], stop=True))
        self.assertEqual([int(o['id']) for o in owner.call('owner/outlets')['actor']['outlets']], [a['id']])
        self.assertEqual(owner.request('owner/switch', dict(restaurant_id=b['id']))[0], 403)
        self.assertEqual(Client(base).request('owner/outlets')[0], 401)


if __name__ == '__main__':
    unittest.main()
