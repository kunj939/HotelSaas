"""Validate the shipped menu, photo licences and important printed price options."""
import json
import hashlib
from urllib.parse import urlparse
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class StarterMenuTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.menu = json.loads((ROOT/'public/assets/kaku_menu.json').read_text(encoding='utf-8'))
        cls.items = {item['name']: item for item in cls.menu['items']}
        cls.credits = json.loads((ROOT/'public/assets/photos/kaku/premium.json').read_text(encoding='utf-8'))

    def test_complete_menu_and_categories(self):
        self.assertEqual(len(self.menu['categories']), 28)
        self.assertEqual(len(self.menu['items']), 194)
        self.assertEqual(len(self.items), 194)
        self.assertEqual(set(self.menu['categories']), {i['category'] for i in self.items.values()})
        for item in self.items.values():
            self.assertEqual(item['food_type'], 'veg')
            self.assertEqual(item['available'], 1)
            self.assertGreater(item['base_price'], 0)
            for field, limit in [('name',160),('description',500),('image_url',800),('image_credit',500),('image_license',100),('image_source_url',800)]:
                self.assertLessEqual(len(item[field]), limit, (item['name'],field))
            if item['options']:
                self.assertEqual(item['base_price'], min(o['price'] for o in item['options']))

    def test_printed_prices_and_portion_choices(self):
        expected = {
            'Cheese Capsicum Pizza': [('Small',120),('Medium',210),('Large',280)],
            'All Topping Pizza': [('Small',250),('Medium',360),('Large',470)],
            'Plain Roti': [('Tawa',8),('Tandoor',12)],
            'Butter Roti': [('Tawa',10),('Tandoor',14)],
            'Plain Rice': [('Half',60),('Full',90)],
            'Paneer Butter Masala': [('Half',170),('Full',250)],
            'Sarson Saag': [('Half',130),('Full',200)],
            'Manchurian': [('Gravy',230),('Dry',230)],
        }
        for name, options in expected.items():
            self.assertEqual([(o['name'],o['price']) for o in self.items[name]['options']], options)
        self.assertEqual(self.items['Kaku Maharaja Thali']['base_price'],330)
        self.assertEqual(self.items['Jeera Rice']['options'],[])

    def test_photos_are_local_and_credited(self):
        credits_by_file={c['file']:c for c in self.credits.values()}
        self.assertEqual(set(self.credits),set(self.items))
        self.assertEqual(len(credits_by_file),194)
        hashes=set()
        sources=set()
        for item in self.items.values():
            path=ROOT/'public'/item['image_url']
            self.assertTrue(path.is_file(),path)
            self.assertGreater(path.stat().st_size,2000,path)
            self.assertEqual(path.read_bytes()[:2],b'\xff\xd8',path)
            hashes.add(hashlib.sha256(path.read_bytes()).hexdigest())
            credit=credits_by_file[item['image_url']]
            self.assertEqual(item['image_credit'],credit['artist'])
            self.assertEqual(item['image_license'],credit['license'])
            self.assertEqual(item['image_source_url'],credit['source'])
            self.assertTrue(credit['artist'])
            if credit['license']=='AI-generated representative image':
                self.assertEqual(credit['artist'],'AI-generated')
                self.assertEqual(credit['source'],'')
                self.assertEqual(credit['license_url'],'')
                self.assertEqual(credit['generation_tool'],'built-in image_gen')
                self.assertTrue(credit['prompt'])
                sources.add(credit['file'])
            else:
                self.assertIn(urlparse(credit['source']).hostname,('commons.wikimedia.org','www.pexels.com','www.flickr.com','wordpress.org'))
                self.assertTrue(credit['license'].startswith('CC BY') or credit['license'] in ('CC0','Public domain','Pexels License'))
                self.assertTrue(credit['license_url'].startswith('https://'))
                sources.add(credit['source'])
        self.assertEqual(len(hashes),194,'Every dish needs a different photo, not renamed duplicate bytes')
        self.assertEqual(len(sources),194,'Do not reuse one source photo across different dishes')

    def test_reviewed_replacements_and_retained_photos(self):
        audit=json.loads((ROOT/'catalog/kaku_photo_audit.json').read_text(encoding='utf-8'))
        previous=json.loads((ROOT/'catalog/kaku_photo_upgrade_v2.json').read_text(encoding='utf-8'))
        self.assertEqual({r['name'] for r in audit['items']},set(self.items))
        self.assertEqual(audit['reviewed_count'],194)
        self.assertEqual(audit['replacement_count'],sum(r['decision']=='replace' for r in audit['items']))
        for review in audit['items']:
            name=review['name']
            self.assertTrue(review['reason'])
            if review['decision']=='replace':
                self.assertEqual(self.items[name]['image_url'],review['output'])
                self.assertEqual(self.credits[name]['prompt'],review['prompt'])
            else:
                for field,value in previous[name].items():
                    self.assertEqual(self.items[name][field],value)


if __name__ == '__main__':
    unittest.main()
