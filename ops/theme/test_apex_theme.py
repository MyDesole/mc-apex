import importlib.util,sys,unittest
from pathlib import Path
sys.path.insert(0,str(Path(__file__).parent))
from apex_theme import text,TITLES,class_file

class ThemeTests(unittest.TestCase):
 def test_rank_team_and_single_color_codes_are_preserved(self):
  for s in ['§6','§b','§aГотово','§cОшибка','§6[VIP] §a{p}','§9Синие','§bГолубые','§c{TeamColor}{TeamName}']:
   self.assertEqual(text(s),s)
 def test_legacy_caption_limit(self):
  for title in TITLES.values():self.assertLessEqual(len(title),32)
 def test_buttons_and_handlers_share_the_same_transformation(self):
  self.assertEqual(text('§e2 команды'),'§f2 команды')
  self.assertEqual(text('§0Донат-меню §8· Цвет'),'§8APEX §5/ §8Профиль §8· Цвет')
 def test_placeholders_untouched(self):
  import re
  value='§6Карта: §b{map} §e{online}/{max}'
  self.assertEqual(re.findall(r'\{[^{}]+\}',value),re.findall(r'\{[^{}]+\}',text(value)))
 def test_alternate_color_codes(self):
  self.assertEqual(text('&6&lТОП ПОБЕД'),'&d&lТОП ПОБЕД')
  self.assertEqual(text('&b'),'&b')
  self.assertEqual(text('&6[VIP] {p}'),'&6[VIP] {p}')
 def test_idempotent(self):
  for s in ['§6§lAPEX','§6Меню','§eНажми для выбора']+list(TITLES):self.assertEqual(text(text(s)),text(s))

if __name__=='__main__':unittest.main()
