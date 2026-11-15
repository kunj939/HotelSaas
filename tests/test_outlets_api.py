"""Verify restaurant selection and access isolation on a disposable test site."""
import os
import unittest
import uuid
from test_menu_api import Client


@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'), 'Disposable test site required')
class OutletSelectionTest(unittest.TestCase):
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
