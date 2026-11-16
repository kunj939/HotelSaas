import os
import unittest
import uuid
from test_menu_api import Client

@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'),'Disposable test site required')
class PricingTest(unittest.TestCase):
    def test_default_price_permissions_and_existing_agreements(self):
        base=os.environ['TABLEBLOOM_TEST_URL'];admin=Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'],os.environ['TABLEBLOOM_TEST_PASSWORD'])
        original=admin.call('admin/pricing');original_upi=admin.call('admin/settings')['upi'];key=uuid.uuid4().hex;password=uuid.uuid4().hex
        def create(suffix,**extras):
            payload=dict(name='Pricing QA',slug=key+suffix,email=key+suffix+'@example.test',password=password,**extras)
            result=admin.call('admin/restaurants/create',payload);return result,payload
        existing,_=create('existing',monthly_fee=499)
        try:
            admin.call('admin/settings/save',{'upi_id':'pricing-qa@upi','upi_name':'Pricing QA'})
            admin.call('admin/pricing/save',{'default_monthly_fee':799.50,'default_autopay_fee':699.25})
            self.assertEqual(admin.call('admin/settings')['upi']['id'],'pricing-qa@upi')
            new,payload=create('new')
            owner=Client(base);owner.login(payload['email'],password)
            self.assertEqual(float(owner.call('owner/settings')['restaurant']['monthly_fee']),799.5)
            billing=owner.call('owner/billing')
            self.assertEqual(float(billing['quotes']['monthly']['price']),799.5)
            self.assertEqual(float(billing['quotes']['autopay6']['price']),699.25)
            self.assertEqual(float(billing['upi_quotes']['upfront6']['total']),699.25*6+350)
            utr=str(uuid.uuid4().int)[:12]
            owner.call('owner/billing/upi',{'plan':'upfront6','utr':utr})
            admin.call('admin/pricing/save',{'default_monthly_fee':899,'default_autopay_fee':749})
            payment=next(p for p in admin.call('admin/upi-payments')['payments'] if p['utr']==utr)
            self.assertEqual(float(payment['amount']),699.25*6+350)
            admin.call('admin/upi-payments/approve',{'id':payment['id']})
            billing=owner.call('owner/billing')
            self.assertEqual(float(billing['restaurant']['monthly_fee']),799.5)
            self.assertEqual(float(billing['quotes']['monthly']['total']),799.5)
            self.assertEqual(float(billing['quotes']['autopay6']['price']),749)
            self.assertEqual(owner.request('admin/pricing/save',{'default_monthly_fee':1})[0],401)
            self.assertEqual(Client(base).request('admin/pricing')[0],401)
            rows=admin.call('admin/restaurants')['restaurants'];old=next(r for r in rows if r['id']==existing['id'])
            self.assertEqual(float(old['monthly_fee']),499)
            admin.call('admin/restaurants/update',dict(id=old['id'],status=old['status'],subscription_ends=old['subscription_ends'],monthly_fee=549))
            self.assertEqual(float(next(r for r in admin.call('admin/restaurants')['restaurants'] if r['id']==old['id'])['monthly_fee']),549)
            for invalid in [-1,100001,'abc',None]:self.assertEqual(admin.request('admin/pricing/save',{'default_monthly_fee':invalid})[0],400)
        finally:
            admin.call('admin/pricing/save',original)
            if original_upi:
                admin.call('admin/settings/save',{'upi_id':original_upi['id'],'upi_name':original_upi['name']})

if __name__=='__main__':unittest.main()
