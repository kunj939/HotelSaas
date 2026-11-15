"""Run against a disposable database with the same environment as test_menu_api.py."""
import os
import unittest
import uuid
from test_menu_api import Client

@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'),'Disposable test site required')
class DemoFeaturesTest(unittest.TestCase):
    def test_contact_modes_and_specials(self):
        base=os.environ['TABLEBLOOM_TEST_URL'];admin=Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'],os.environ['TABLEBLOOM_TEST_PASSWORD'])
        token=uuid.uuid4().hex;password=uuid.uuid4().hex
        slug='details-'+token
        admin.call('admin/restaurants/create',dict(name='Details QA',slug=slug,email=token+'@example.test',password=password,menu_template='kaku-v1'))
        owner=Client(base);owner.login(token+'@example.test',password);customer=Client(base)
        item=owner.call('owner/menu')['items'][0];item['featured']=1;owner.call('owner/menu/save',item)
        public=customer.call('public/restaurant',query={'slug':slug})
        self.assertEqual(next(i for i in public['items'] if i['id']==item['id'])['featured'],1)
        settings=owner.call('owner/settings')['restaurant']
        self.assertEqual(public['restaurant']['whatsapp_number'],'919053573091')
        settings.update(whatsapp_number='+91 98765 43210',contact_email='qa@example.test',contact_options_enabled=1)
        def mode(name,phone):
            settings.update(customer_name_mode=name,customer_phone_mode=phone)
            owner.call('owner/settings/save',settings)
        def order(**details):
            return customer.request('public/order',dict(slug=slug,table_no=1,client_request_id=str(uuid.uuid4()),items=[dict(item_id=item['id'],variant=item['options'][0]['name'],quantity=1,addons=[])],**details))
        mode('required','required')
        contact=customer.call('public/restaurant',query={'slug':slug})['restaurant']
        self.assertEqual(contact['whatsapp_number'],'919876543210')
        self.assertEqual(contact['contact_email'],'qa@example.test')
        self.assertEqual(order()[0],400)
        self.assertEqual(order(customer_name='QA Guest')[0],400)
        self.assertEqual(order(customer_name='QA Guest',customer_phone='123')[0],400)
        code,data=order(customer_name='QA Guest',customer_phone='+91 9876543210');self.assertEqual(code,201)
        rows=owner.call('owner/orders')['orders'];saved=next(o for o in rows if o['id']==data['order']['id'])
        self.assertEqual(saved['customer_phone'],'9876543210')
        public_status=customer.call('public/status',query={'id':data['order']['id'],'key':data['order']['tracking_key']})['order']
        self.assertNotIn('customer_phone',public_status);self.assertNotIn('customer_name',public_status)
        mode('optional','optional');self.assertEqual(order()[0],201)
        mode('off','off');code,data=order(customer_name='Must not store',customer_phone='123');self.assertEqual(code,201)
        saved=next(o for o in owner.call('owner/orders')['orders'] if o['id']==data['order']['id'])
        self.assertIsNone(saved['customer_name']);self.assertIsNone(saved['customer_phone'])
        mode('off','required');self.assertEqual(order()[0],400)
        public=customer.call('public/restaurant',query={'slug':slug})
        self.assertEqual(public['restaurant']['customer_phone_mode'],'required')
        invalid=dict(settings,customer_name_mode='invalid');self.assertEqual(owner.request('owner/settings/save',invalid)[0],400)
        invalid=dict(settings,whatsapp_number='123');self.assertEqual(owner.request('owner/settings/save',invalid)[0],400)
        invalid=dict(settings,contact_email='javascript:bad');self.assertEqual(owner.request('owner/settings/save',invalid)[0],400)
        settings['contact_options_enabled']=0;mode('optional','optional')
        self.assertEqual(customer.call('public/restaurant',query={'slug':slug})['restaurant']['contact_options_enabled'],0)
        item['featured']=0;owner.call('owner/menu/save',item)
        self.assertEqual(next(i for i in owner.call('owner/menu')['items'] if i['id']==item['id'])['featured'],0)

if __name__=='__main__':unittest.main()
