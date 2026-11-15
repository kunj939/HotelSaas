"""Validate the shipped menu, photo licences and important printed price options."""
import json
from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]


class StarterMenuTest(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.menu = json.loads((ROOT/'public/assets/kaku_menu.json').read_text(encoding='utf-8'))
        cls.items = {item['name']: item for item in cls.menu['items']}
        cls.credits = json.loads((ROOT/'public/assets/photos/kaku/credits.json').read_text(encoding='utf-8'))

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
        for item in self.items.values():
            path=ROOT/'public'/item['image_url']
            self.assertTrue(path.is_file(),path)
            self.assertGreater(path.stat().st_size,2000,path)
            self.assertEqual(path.read_bytes()[:2],b'\xff\xd8',path)
            credit=credits_by_file[item['image_url']]
            self.assertEqual(item['image_credit'],credit['artist'])
            self.assertEqual(item['image_license'],credit['license'])
            self.assertEqual(item['image_source_url'],credit['source'])
            self.assertTrue(credit['source'].startswith('https://commons.wikimedia.org/'))
            self.assertTrue(credit['license'].startswith('CC BY') or credit['license'] in ('CC0','Public domain'))


if __name__ == '__main__':
    unittest.main()
