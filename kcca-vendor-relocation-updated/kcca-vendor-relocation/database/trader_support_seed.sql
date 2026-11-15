-- =====================================================================
-- KCCA VRSP — prescribed attire and the support catalogue
--
-- The colour is not decoration. A red apron reads as a butcher and a white
-- one as a cook from across a market, which is what makes the rule visible
-- without anybody having to read a notice. issued_by records whose hand
-- supplies it: the Authority in a public market, the chairman in a private
-- one, where the Authority has no presence to hand anything over.
-- =====================================================================

DELETE FROM `support_items`;
DELETE FROM `trade_attire`;

INSERT INTO `trade_attire`
  (`trade_code`, `garment`, `colour`, `colour_hex`, `unit_price_ugx`, `issued_by`, `sizes`, `notes`) VALUES
  ('meat-fish',         'Butcher''s apron and sleeve protectors', 'Red',              '#C0261A', 45000, 'kcca',     'One size',
   'Blood-borne hazard: the apron is the PPE, not a uniform.'),
  ('food-cooking',      'Cook''s jacket and cap',               'White',            '#F5F5F5', 35000, 'kcca',     'S–XXL',
   'White shows soil immediately, which is the point of specifying it.'),
  ('beverages',         'Bottler''s apron',                      'Amber',            '#EAA515', 30000, 'kcca',     'One size', NULL),
  ('fresh-produce',     'Produce seller''s apron',               'Green',            '#1B7F4D', 30000, 'kcca',     'One size', NULL),
  ('crops-grains',      'Grain trader''s coat',                  'Khaki',            '#B08968', 32000, 'kcca',     'S–XXL',
   'Sacks are heavy; the coat is issued for lifting as much as for looks.'),
  ('livestock',         'Stock handler''s apron',                'Olive',            '#6B7A3A', 30000, 'kcca',     'One size', NULL),
  ('household-goods',   'Trader''s smock',                        'Slate',            '#464646', 28000, 'kcca',     'S–XXL', NULL),
  ('clothing-textiles', 'Trader''s smock',                        'Plum',             '#6D3B5C', 28000, 'kcca',     'S–XXL', NULL),
  ('electronics-repairs','Technician''s apron',                   'Navy',             '#1D3A6E', 32000, 'kcca',     'S–XXL',
   'Solder and acid risk.'),
  ('salon-services',    'Salon coat',                            'Charcoal',         '#3A3F47', 30000, 'chairman','S–L',
   'Salon trade: supplied by the market chairman, not the Authority.'),
  ('mobile-services',   'Agent''s tabard',                        'Teal',             '#2F8F7B', 22000, 'chairman','S–XXL', NULL),
  ('handicraft',        'Craft apron',                            'Sand',             '#D9C7A3', 25000, 'chairman','One size', NULL),
  ('other',             'Standard trader''s apron',              'Charcoal',         '#464646', 25000, 'kcca',     'S–XXL',
   'The default for any trade not separately prescribed.');

INSERT INTO `support_items`
  (`code`, `name`, `category`, `unit_price_ugx`, `requires_receipt`, `notes`) VALUES
  ('AT-STD',  'Prescribed workwear set',        'attire',       0,      1, 'Priced per trade in trade_attire.'),
  ('NET-MOS', 'Long-lasting insecticidal net', 'mosquito-net', 45000, 1, 'Malaria prevention; issued before the rains.'),
  ('NET-ORD', 'Ordinary bed net',              'mosquito-net', 30000, 1, NULL),
  ('FOOD-RAT','Rations — one week',            'food',         55000, 1, 'Issued to a trader who asks while the food is going to spoil.'),
  ('BIN-240', '240-litre dustbin',            'sanitation',   85000, 1, 'Keeps the trading bay clear of refuse.'),
  ('BIN-140', '140-litre dustbin',            'sanitation',   55000, 1, NULL),
  ('TRAY-01', 'Stainless steel trading tray', 'tools',        40000, 1, NULL),
  ('SCALE-5', '5 kg platform scale',          'tools',        95000, 1, 'Prevents short weight, which is why the Authority supplies it.'),
  ('CRATE-01','Stackable produce crate',      'tools',        25000, 1, NULL),
  ('TARP-01', 'Tarpaulin and pegs',           'tools',        40000, 1, 'Cover for produce in the afternoon sun.');