"""Exercise onboarding and menu ownership against a disposable MySQL-backed test site.

Requires TABLEBLOOM_TEST_URL, TABLEBLOOM_TEST_EMAIL, TABLEBLOOM_TEST_PASSWORD.
Creates test accounts and orders. Never target a production database.
Uses Python's standard library only.
"""
import http.cookiejar
import json
import os
import unittest
import urllib.error
import urllib.parse
import urllib.request
import uuid


class Client:
    def __init__(self, base):
        self.base=base.rstrip('/')+'/api/index.php?route='
        self.opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
        self.csrf=''
        self.csrf=self.call('bootstrap')['csrf']

    def request(self, route, data=None, query=None):
        url=self.base+urllib.parse.quote(route, safe='')
        if query: url+='&'+urllib.parse.urlencode(query)
        req=urllib.request.Request(url, data=json.dumps(data).encode() if data is not None else None,
                                   headers={'Content-Type':'application/json','X-CSRF-Token':self.csrf})
        try:
            with self.opener.open(req,timeout=30) as res:return res.status,json.load(res)
        except urllib.error.HTTPError as e:return e.code,json.load(e)

    def call(self, route, data=None, query=None):
        code,result=self.request(route,data,query)
        if code>=400:raise AssertionError((route,code,result))
        return result

    def login(self,email,password):
        self.csrf=self.call('auth/login',{'email':email,'password':password})['csrf']


@unittest.skipUnless(os.getenv('TABLEBLOOM_TEST_URL'), 'Set disposable test-site environment variables')
class MenuApiTest(unittest.TestCase):
    def test_onboarding_edit_availability_and_isolation(self):
        base=os.environ['TABLEBLOOM_TEST_URL']; admin=Client(base)
        admin.login(os.environ['TABLEBLOOM_TEST_EMAIL'],os.environ['TABLEBLOOM_TEST_PASSWORD'])
        token=uuid.uuid4().hex[:12]; password=uuid.uuid4().hex
        for suffix, extra in [('default',{}),('empty',{'menu_template':'none'})]:
            payload=dict(name='Another Restaurant',slug=f'plain-test-{token}-{suffix}',email=f'{token}-{suffix}@example.test',password=password,**extra)
            created=admin.call('admin/restaurants/create',payload)
            self.assertEqual(created['starter_menu'],{'template':'none','items':0,'categories':5})
            plain=Client(base).call('public/restaurant',query={'slug':payload['slug']})
            self.assertEqual(plain['items'],[])
            self.assertEqual(len(plain['categories']),5)
        invalid=dict(payload,slug=f'invalid-test-{token}',email=f'{token}-invalid@example.test',menu_template='unknown')
        self.assertEqual(admin.request('admin/restaurants/create',invalid)[0],400)
        self.assertEqual(Client(base).request('public/restaurant',query={'slug':invalid['slug']})[0],404)
        payloads=[]; owners=[]
        for suffix in ['a','b']:
            payload=dict(name='Kaku Test '+suffix,slug=f'kaku-test-{token}-{suffix}',email=f'{token}-{suffix}@example.test',password=password,table_count=20,monthly_fee=599,menu_template='kaku-v1')
            created=admin.call('admin/restaurants/create',payload)
            self.assertEqual(created['starter_menu']['items'],194)
            self.assertEqual(created['starter_menu']['categories'],28)
            owner=Client(base);owner.login(payload['email'],password)
            owners.append(owner);payloads.append(payload)
        a,b=owners
        menu=a.call('owner/menu'); other=b.call('owner/menu')
        self.assertEqual(len(menu['items']),194);self.assertEqual(len(other['items']),194)
        self.assertTrue(set(i['id'] for i in menu['items']).isdisjoint(i['id'] for i in other['items']))
        pizza=next(i for i in menu['items'] if i['name']=='Cheese Capsicum Pizza')
        other_pizza=next(i for i in other['items'] if i['name']==pizza['name'])
        pizza['options'][1]['price']=235
        a.call('owner/menu/save',pizza)  # Keeping the bundled photo must be accepted.
        self.assertEqual(b.call('owner/menu')['items'][0]['image_url'],other['items'][0]['image_url'])
        saved=next(i for i in b.call('owner/menu')['items'] if i['id']==other_pizza['id'])
        self.assertEqual(saved['options'][1]['price'],210)
        self.assertEqual(b.request('owner/menu/save',pizza)[0],400)  # A's category is not B's.
        foreign_item=dict(pizza,category_id=other_pizza['category_id'])
        self.assertEqual(b.request('owner/menu/save',foreign_item)[0],404)
        b.call('owner/menu/toggle',{'id':pizza['id']})
        self.assertEqual(next(i for i in a.call('owner/menu')['items'] if i['id']==pizza['id'])['available'],1)
        customer=Client(base)
        order=dict(slug=payloads[0]['slug'],table_no=2,client_request_id=str(uuid.uuid4()),customer_name='Menu QA',items=[dict(item_id=pizza['id'],variant='Medium',quantity=2,addons=[],price=1)])
        placed=customer.call('public/order',order)['order']
        self.assertEqual(float(placed['total']),470)  # Server prices the selected size.
        a.call('owner/menu/toggle',{'id':pizza['id']})
        public=customer.call('public/restaurant',query={'slug':payloads[0]['slug']})
        self.assertEqual(next(i for i in public['items'] if i['id']==pizza['id'])['available'],0)
        order['client_request_id']=str(uuid.uuid4())
        self.assertEqual(customer.request('public/order',order)[0],400)
        a.login(payloads[0]['email'],password)
        self.assertEqual(len(a.call('owner/menu')['items']),194)
        self.assertEqual(next(i for i in a.call('owner/menu')['items'] if i['id']==pizza['id'])['available'],0)
        a.call('owner/menu/toggle',{'id':pizza['id']})
        self.assertEqual(float(customer.call('public/order',order)['order']['total']),470)
        created=a.call('owner/menu/save',dict(name='Owner Special',category_id=menu['categories'][0]['id'],base_price=99,food_type='veg',available=1))
        self.assertEqual(len(a.call('owner/menu')['items']),195)
        a.call('owner/menu/delete',{'id':created['id']})
        a.call('owner/menu/delete',{'id':pizza['id']})
        a.login(payloads[0]['email'],password)
        self.assertEqual(len(a.call('owner/menu')['items']),193)
        self.assertEqual(len(b.call('owner/menu')['items']),194)
        duplicate=dict(payloads[0],slug=f'kaku-test-{token}-rollback')
        self.assertEqual(admin.request('admin/restaurants/create',duplicate)[0],400)
        self.assertEqual(customer.request('public/restaurant',query={'slug':duplicate['slug']})[0],404)
        print('PASS: blank default menus; explicit Kaku selection; invalid template rejection; independent menus; photo-preserving edits; tenant isolation; size pricing; sold-out/restock; persistence; rollback')


if __name__=='__main__':unittest.main()
