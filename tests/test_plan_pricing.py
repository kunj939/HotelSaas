import os
import unittest
import uuid
from test_menu_api import Client

@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'),'Disposable test site required')
class PricingTest(unittest.TestCase):
    def test_default_price_permissions_and_existing_agreements(self):
        base=os.environ['TABLEBLOOM_TEST_URL'];admin=Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'],os.environ['TABLEBLOOM_TEST_PASSWORD'])
        original=admin.call('admin/pricing')['default_monthly_fee'];key=uuid.uuid4().hex;password=uuid.uuid4().hex
        def create(suffix,**extras):
            payload=dict(name='Pricing QA',slug=key+suffix,email=key+suffix+'@example.test',password=password,**extras)
            result=admin.call('admin/restaurants/create',payload);return result,payload
        existing,_=create('existing',monthly_fee=499)
        try:
            admin.call('admin/pricing/save',{'default_monthly_fee':799.50})
            new,payload=create('new')
            owner=Client(base);owner.login(payload['email'],password)
            self.assertEqual(float(owner.call('owner/settings')['restaurant']['monthly_fee']),799.5)
            self.assertEqual(owner.request('admin/pricing/save',{'default_monthly_fee':1})[0],401)
            self.assertEqual(Client(base).request('admin/pricing')[0],401)
            rows=admin.call('admin/restaurants')['restaurants'];old=next(r for r in rows if r['id']==existing['id'])
            self.assertEqual(float(old['monthly_fee']),499)
            admin.call('admin/restaurants/update',dict(id=old['id'],status=old['status'],subscription_ends=old['subscription_ends'],monthly_fee=549))
            self.assertEqual(float(next(r for r in admin.call('admin/restaurants')['restaurants'] if r['id']==old['id'])['monthly_fee']),549)
            for invalid in [-1,100001,'abc',None]:self.assertEqual(admin.request('admin/pricing/save',{'default_monthly_fee':invalid})[0],400)
        finally:admin.call('admin/pricing/save',{'default_monthly_fee':original})

if __name__=='__main__':unittest.main()
