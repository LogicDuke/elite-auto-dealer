#!/usr/bin/env python3
"""Builds data/vehicle-catalogue.json, the default Make -> Model family catalogue.

This file is the editable source of the catalogue. Edit CATALOGUE / EXPLICIT_SLUGS below, then:

    python bin/build-vehicle-catalogue.py           # regenerate data/vehicle-catalogue.json
    python bin/build-vehicle-catalogue.py --check   # exit 1 if the JSON is not up to date

Run from the theme root (or anywhere: paths are resolved from this file). Standard library only,
no network access. Output is deterministic: makes sorted by slug, models in the order listed here.

When the catalogue gains entries, also raise VERSION here and EDA_CATALOGUE_VERSION in
inc/vehicle-catalogue.php (see docs/VEHICLE-CATALOGUE.md).

Rules:
- Model families only. Trims, engines and packages belong in the vehicle Variant field.
- Model slugs must be unique across the whole catalogue (one WordPress taxonomy). Names shared by
  two makes get make-prefixed slugs automatically; EXPLICIT_SLUGS covers names that would
  slugify badly. Slugs mirror WordPress sanitize_title(); tests/catalogue-test.php verifies them
  with the real WordPress function.
"""
import json
import re
import sys
import unicodedata
from collections import Counter
from pathlib import Path

VERSION = 1

DESCRIPTION = (
    'Default Make -> Model family catalogue for the Belgian/European used-car market. '
    'Model families only: trims and engines belong in the vehicle Variant field. '
    'Strings use the WordPress sanitize_title() slug; objects give an explicit slug '
    '(names shared by two makes, or names that would not slugify cleanly).'
)

CATALOGUE = {
    'Abarth': ['500', '500e', '595', '695', '600e', '124 Spider'],
    'Alfa Romeo': ['MiTo', 'Giulietta', 'Giulia', 'Stelvio', 'Tonale', 'Junior', '4C'],
    'Alpine': ['A110', 'A290'],
    'Aston Martin': ['Vantage', 'DB11', 'DB12', 'DBS', 'DBX'],
    'Audi': ['A1', 'A3', 'A4', 'A5', 'A6', 'A6 e-tron', 'A7', 'A8', 'Q2', 'Q3', 'Q4 e-tron', 'Q5', 'Q6 e-tron', 'Q7', 'Q8', 'Q8 e-tron', 'e-tron GT', 'TT', 'R8', 'RS 3', 'RS 4', 'RS 5', 'RS 6', 'RS 7', 'RS Q3', 'RS Q8'],
    'Bentley': ['Continental GT', 'Flying Spur', 'Bentayga'],
    'BMW': ['1 Series', '2 Series', '3 Series', '4 Series', '5 Series', '6 Series', '7 Series', '8 Series', 'X1', 'X2', 'X3', 'X4', 'X5', 'X6', 'X7', 'XM', 'Z4', 'M2', 'M3', 'M4', 'M5', 'M8', 'i3', 'i4', 'i5', 'i7', 'i8', 'iX', 'iX1', 'iX2', 'iX3'],
    'BYD': ['Dolphin', 'Dolphin Surf', 'Atto 2', 'Atto 3', 'Seal', 'Seal U', 'Sealion 7', 'Han', 'Tang'],
    'Citroën': ['C1', 'C3', 'C3 Aircross', 'C4', 'C4 X', 'C5 Aircross', 'C5 X', 'Berlingo', 'SpaceTourer', 'Ami'],
    'Cupra': ['Born', 'Formentor', 'Leon', 'Ateca', 'Tavascan', 'Terramar'],
    'Dacia': ['Sandero', 'Logan', 'Duster', 'Jogger', 'Spring', 'Bigster'],
    'DS Automobiles': ['DS 3', 'DS 4', 'DS 7', 'DS 9'],
    'Ferrari': ['Portofino', 'Roma', 'F8', '296', 'SF90', '812', 'Purosangue', '12Cilindri'],
    'Fiat': ['500', '500e', '500X', '500L', '600', 'Panda', 'Tipo', 'Doblò'],
    'Ford': ['Fiesta', 'Focus', 'Puma', 'EcoSport', 'Kuga', 'Mondeo', 'S-MAX', 'Galaxy', 'Explorer', 'Capri', 'Mustang', 'Mustang Mach-E', 'Ranger', 'Tourneo Connect', 'Transit Custom'],
    'Genesis': ['G70', 'G80', 'GV60', 'GV70', 'GV80'],
    'Honda': ['Jazz', 'Civic', 'HR-V', 'ZR-V', 'CR-V', 'e', 'e:Ny1'],
    'Hyundai': ['i10', 'i20', 'i30', 'Bayon', 'Kona', 'Tucson', 'Santa Fe', 'IONIQ', 'IONIQ 5', 'IONIQ 6', 'Inster'],
    'Jaguar': ['XE', 'XF', 'E-Pace', 'F-Pace', 'I-Pace', 'F-Type'],
    'Jeep': ['Avenger', 'Renegade', 'Compass', 'Wrangler', 'Grand Cherokee'],
    'Kia': ['Picanto', 'Rio', 'Ceed', 'XCeed', 'ProCeed', 'Stonic', 'Niro', 'Sportage', 'Sorento', 'Stinger', 'EV3', 'EV6', 'EV9'],
    'Lamborghini': ['Huracán', 'Aventador', 'Urus', 'Revuelto'],
    'Land Rover': ['Defender', 'Discovery', 'Discovery Sport', 'Range Rover', 'Range Rover Sport', 'Range Rover Velar', 'Range Rover Evoque'],
    'Lexus': ['CT', 'IS', 'ES', 'LS', 'LC', 'LBX', 'UX', 'NX', 'RX', 'RZ'],
    'Lotus': ['Elise', 'Exige', 'Evora', 'Emira', 'Eletre', 'Emeya'],
    'Lynk & Co': ['01', '02', '08'],
    'Maserati': ['Ghibli', 'Quattroporte', 'Levante', 'Grecale', 'GranTurismo', 'MC20'],
    'Mazda': ['Mazda2', 'Mazda3', 'Mazda6', 'CX-3', 'CX-30', 'CX-5', 'CX-60', 'CX-80', 'MX-5', 'MX-30'],
    'McLaren': ['570S', '720S', '750S', 'Artura', 'GT'],
    'Mercedes-Benz': ['A-Class', 'B-Class', 'C-Class', 'CLA', 'CLS', 'E-Class', 'S-Class', 'GLA', 'GLB', 'GLC', 'GLE', 'GLS', 'G-Class', 'SL', 'AMG GT', 'EQA', 'EQB', 'EQC', 'EQE', 'EQE SUV', 'EQS', 'EQS SUV', 'T-Class', 'V-Class'],
    'MG': ['MG3', 'MG4', 'MG5', 'ZS', 'HS', 'Marvel R', 'Cyberster'],
    'MINI': ['Cooper', 'Cabrio', 'Clubman', 'Countryman', 'Aceman', 'Paceman'],
    'Mitsubishi': ['Space Star', 'Colt', 'ASX', 'Eclipse Cross', 'Outlander'],
    'Nissan': ['Micra', 'Note', 'Juke', 'Qashqai', 'X-Trail', 'Leaf', 'Ariya', 'Navara'],
    'Opel': ['Corsa', 'Astra', 'Insignia', 'Mokka', 'Crossland', 'Grandland', 'Frontera', 'Zafira', 'Combo'],
    'Peugeot': ['108', '208', '308', '408', '508', '2008', '3008', '5008', 'Rifter', 'Traveller'],
    'Polestar': ['2', '3', '4'],
    'Porsche': ['718 Boxster', '718 Cayman', '911', 'Taycan', 'Panamera', 'Macan', 'Cayenne'],
    'Renault': ['Twingo', 'Clio', 'Captur', 'Mégane', 'Mégane E-Tech', 'Arkana', 'Austral', 'Rafale', 'Espace', 'Scénic', 'Symbioz', '5', 'Zoe', 'Kangoo'],
    'Rolls-Royce': ['Ghost', 'Phantom', 'Wraith', 'Dawn', 'Cullinan', 'Spectre'],
    'SEAT': ['Mii', 'Ibiza', 'Arona', 'Leon', 'Ateca', 'Tarraco'],
    'Škoda': ['Citigo', 'Fabia', 'Scala', 'Octavia', 'Superb', 'Kamiq', 'Karoq', 'Kodiaq', 'Enyaq', 'Elroq'],
    'Smart': ['ForTwo', 'ForFour', '#1', '#3'],
    'Subaru': ['Impreza', 'XV', 'Crosstrek', 'Forester', 'Outback', 'Solterra', 'BRZ'],
    'Suzuki': ['Ignis', 'Swift', 'Vitara', 'S-Cross', 'Jimny', 'Across', 'Swace'],
    'Tesla': ['Model 3', 'Model S', 'Model X', 'Model Y'],
    'Toyota': ['Aygo X', 'Yaris', 'Yaris Cross', 'GR Yaris', 'Corolla', 'Corolla Cross', 'C-HR', 'Prius', 'Camry', 'RAV4', 'bZ4X', 'Highlander', 'Land Cruiser', 'Hilux', 'Supra', 'GR86'],
    'Volkswagen': ['up!', 'Polo', 'Golf', 'T-Cross', 'Taigo', 'T-Roc', 'Tiguan', 'Touran', 'Sharan', 'Passat', 'Arteon', 'Touareg', 'ID.3', 'ID.4', 'ID.5', 'ID.7', 'ID. Buzz', 'Caddy', 'Multivan'],
    'Volvo': ['V40', 'V60', 'V90', 'S60', 'S90', 'XC40', 'XC60', 'XC90', 'C40', 'EX30', 'EX40', 'EX90'],
}

# Readable URL slugs for names that are bare numbers or symbols (or would collide).
EXPLICIT_SLUGS = {
    ('Smart', '#1'): 'smart-1',
    ('Smart', '#3'): 'smart-3',
    ('Polestar', '2'): 'polestar-2',
    ('Polestar', '3'): 'polestar-3',
    ('Polestar', '4'): 'polestar-4',
    ('Lynk & Co', '01'): 'lynk-co-01',
    ('Lynk & Co', '02'): 'lynk-co-02',
    ('Lynk & Co', '08'): 'lynk-co-08',
    ('Renault', '5'): 'renault-5',
    ('Honda', 'e'): 'honda-e',
}

ROOT = Path(__file__).resolve().parent.parent
OUTPUT = ROOT / 'data' / 'vehicle-catalogue.json'


def slugify(text):
    """Approximation of WordPress sanitize_title() for plain names (accents removed, a-z0-9 and dashes)."""
    text = unicodedata.normalize('NFKD', text).encode('ascii', 'ignore').decode()
    text = re.sub(r'[^a-z0-9\s-]', '', text.lower())
    return re.sub(r'[\s-]+', '-', text).strip('-')


def validate():
    errors = []
    make_slugs = Counter(slugify(make.replace('&', '')) for make in CATALOGUE)
    errors += [f'duplicate make slug: {s}' for s, n in make_slugs.items() if n > 1]
    for make, models in CATALOGUE.items():
        if not make.strip() or not models:
            errors.append(f'make needs a name and at least one model: {make!r}')
        names = Counter(m.strip().lower() for m in models)
        errors += [f'duplicate model in {make}: {n}' for n, c in names.items() if c > 1]
        errors += [f'empty model name in {make}' for m in models if not m.strip()]
    for (make, model), slug in EXPLICIT_SLUGS.items():
        if model not in CATALOGUE.get(make, []):
            errors.append(f'explicit slug for unknown model: {make} {model}')
        if slug != slugify(slug):
            errors.append(f'explicit slug not normalised: {slug}')
    return errors


def build():
    name_counts = Counter(slugify(m) for models in CATALOGUE.values() for m in models)
    makes = []
    for make, models in sorted(CATALOGUE.items(), key=lambda kv: slugify(kv[0])):
        make_slug = slugify(make.replace('&', ''))
        entries = []
        for model in models:
            slug = slugify(model)
            if (make, model) in EXPLICIT_SLUGS:
                entries.append({'name': model, 'slug': EXPLICIT_SLUGS[(make, model)]})
            elif name_counts[slug] > 1:
                entries.append({'name': model, 'slug': f'{make_slug}-{slug}'})
            else:
                entries.append(model)
        makes.append({'name': make, 'slug': make_slug, 'models': entries})

    slugs = [e['slug'] if isinstance(e, dict) else slugify(e) for m in makes for e in m['models']]
    duplicates = [s for s, n in Counter(slugs).items() if n > 1]
    if duplicates:
        raise SystemExit(f'model slug collision(s) after prefixing: {duplicates}')

    data = {'version': VERSION, 'description': DESCRIPTION, 'makes': makes}
    return json.dumps(data, ensure_ascii=False, indent='\t') + '\n'


def main():
    errors = validate()
    if errors:
        print('\n'.join(errors), file=sys.stderr)
        return 1
    output = build()
    makes = len(CATALOGUE)
    models = sum(len(m) for m in CATALOGUE.values())

    if '--check' in sys.argv[1:]:
        current = OUTPUT.read_text(encoding='utf-8') if OUTPUT.exists() else ''
        if current != output:
            print(f'{OUTPUT.relative_to(ROOT).as_posix()} is out of date: run python bin/build-vehicle-catalogue.py', file=sys.stderr)
            return 1
        print(f'OK: {OUTPUT.relative_to(ROOT).as_posix()} matches the generator ({makes} makes, {models} models).')
        return 0

    OUTPUT.write_text(output, encoding='utf-8', newline='\n')
    print(f'Wrote {OUTPUT.relative_to(ROOT).as_posix()} ({makes} makes, {models} models).')
    return 0


if __name__ == '__main__':
    sys.exit(main())
