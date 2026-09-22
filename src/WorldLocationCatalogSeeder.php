<?php
declare(strict_types=1);

final class WorldLocationCatalogSeeder
{
    private const MARKER = 'world_locations_small_medium_v2';
    private const LOCK = 'oilcorp_world_locations_v2';

    // EN: Region code, location, country, coordinates, richness, type, tier and entry cost.
    // PL: Kod regionu, lokalizacja, kraj, wspolrzedne, zasobnosc, typ, poziom i koszt wejscia.
    private const LOCATIONS = [
        ['middle_east', 'Masjed Soleyman', 'IR', 31.93, 49.31, 0.72, 'onshore', 'starter', 1400000],
        ['middle_east', 'Awali', 'BH', 26.05, 50.55, 0.78, 'onshore', 'starter', 1600000],
        ['middle_east', 'Asab', 'AE', 23.98, 53.86, 1.12, 'onshore', 'medium', 3800000],
        ['russia', 'Ukhta', 'RU', 63.57, 53.70, 0.69, 'onshore', 'starter', 1350000],
        ['russia', 'Orenburg', 'RU', 51.79, 55.10, 0.81, 'onshore', 'starter', 1750000],
        ['russia', 'Nizhnevartovsk', 'RU', 60.94, 76.55, 1.18, 'onshore', 'medium', 3900000],
        ['africa', 'Albertine Graben', 'UG', 1.90, 31.40, 0.73, 'onshore', 'starter', 1250000],
        ['africa', 'Agadem', 'NE', 16.90, 12.80, 0.77, 'onshore', 'starter', 1450000],
        ['africa', 'Lokichar', 'KE', 2.70, 35.80, 1.09, 'onshore', 'medium', 3200000],
        ['usa_canada', 'Osage County', 'US', 36.60, -96.40, 0.70, 'onshore', 'starter', 1650000],
        ['usa_canada', 'Lloydminster', 'CA', 53.28, -110.00, 0.80, 'onshore', 'starter', 1950000],
        ['usa_canada', 'Anadarko Basin', 'US', 35.55, -98.15, 1.15, 'onshore', 'medium', 4200000],
        ['north_europe', 'Bobrka', 'PL', 49.62, 21.68, 0.66, 'onshore', 'starter', 1450000],
        ['north_europe', 'Wietze', 'DE', 52.70, 9.83, 0.74, 'onshore', 'starter', 1750000],
        ['north_europe', 'Gorm', 'DK', 55.58, 4.62, 1.16, 'offshore', 'medium', 4500000],
        ['southeast_asia', 'Bula', 'ID', -3.10, 130.50, 0.71, 'onshore', 'starter', 1300000],
        ['southeast_asia', 'Pangkalan Brandan', 'ID', 4.00, 98.30, 0.79, 'onshore', 'starter', 1550000],
        ['southeast_asia', 'Banyuasin', 'ID', -2.60, 104.80, 1.08, 'onshore', 'medium', 3500000],
        ['latam', 'Comodoro Rivadavia', 'AR', -45.86, -67.48, 0.75, 'onshore', 'starter', 1350000],
        ['latam', 'Neiva', 'CO', 2.94, -75.29, 0.82, 'onshore', 'starter', 1550000],
        ['latam', 'Magallanes', 'CL', -52.50, -69.70, 1.11, 'onshore', 'medium', 3400000],
        ['middle_east', 'Naft Shahr', 'IR', 33.99, 46.00, 0.70, 'onshore', 'starter', 1450000],
        ['middle_east', 'Gachsaran', 'IR', 30.36, 50.80, 0.79, 'onshore', 'starter', 1750000],
        ['middle_east', 'Abqaiq', 'SA', 25.94, 49.67, 0.75, 'onshore', 'starter', 1700000],
        ['middle_east', 'Qatif', 'SA', 26.57, 49.99, 0.81, 'onshore', 'starter', 1850000],
        ['middle_east', 'Burgan East', 'KW', 28.86, 47.99, 0.76, 'onshore', 'starter', 1600000],
        ['middle_east', 'Rumaila North', 'IQ', 30.70, 47.36, 0.82, 'onshore', 'starter', 1550000],
        ['middle_east', 'Dukhan South', 'QA', 25.39, 50.75, 0.73, 'onshore', 'starter', 1650000],
        ['middle_east', 'Marmul', 'OM', 18.14, 55.23, 0.77, 'onshore', 'starter', 1650000],
        ['middle_east', 'Kharg Island', 'IR', 29.25, 50.33, 1.10, 'onshore', 'medium', 3600000],
        ['middle_east', 'Kirkuk South', 'IQ', 35.28, 44.39, 1.14, 'onshore', 'medium', 3550000],
        ['middle_east', 'Safaniya Coast', 'SA', 27.98, 48.68, 1.17, 'offshore', 'medium', 4300000],
        ['middle_east', 'Habshan', 'AE', 23.88, 53.71, 1.13, 'onshore', 'medium', 3950000],
        ['middle_east', 'Fahud', 'OM', 22.31, 56.48, 1.09, 'onshore', 'medium', 3600000],
        ['middle_east', 'Wafra', 'KW', 28.56, 48.06, 1.11, 'onshore', 'medium', 3700000],
        ['middle_east', 'Ras Laffan', 'QA', 25.92, 51.53, 1.08, 'offshore', 'medium', 4200000],
        ['middle_east', 'Basra West', 'IQ', 30.49, 47.62, 1.15, 'onshore', 'medium', 3800000],
        ['middle_east', 'Saih Rawl', 'OM', 21.82, 56.61, 1.12, 'onshore', 'medium', 3750000],
        ['russia', 'Usinsk', 'RU', 65.99, 57.53, 0.68, 'onshore', 'starter', 1500000],
        ['russia', 'Naryan-Mar', 'RU', 67.64, 53.01, 0.72, 'onshore', 'starter', 1550000],
        ['russia', 'Perm', 'RU', 58.00, 56.31, 0.78, 'onshore', 'starter', 1650000],
        ['russia', 'Buguruslan', 'RU', 53.65, 52.43, 0.76, 'onshore', 'starter', 1450000],
        ['russia', 'Samara East', 'RU', 53.20, 50.28, 0.81, 'onshore', 'starter', 1800000],
        ['russia', 'Tomsk', 'RU', 56.48, 84.95, 0.75, 'onshore', 'starter', 1650000],
        ['russia', 'Nyagan', 'RU', 62.14, 65.39, 0.79, 'onshore', 'starter', 1750000],
        ['russia', 'Tuymazy', 'RU', 54.61, 53.69, 0.73, 'onshore', 'starter', 1500000],
        ['russia', 'Khanty-Mansiysk', 'RU', 61.00, 69.00, 1.10, 'onshore', 'medium', 3600000],
        ['russia', 'Surgut East', 'RU', 61.26, 73.42, 1.14, 'onshore', 'medium', 3900000],
        ['russia', 'Noyabrsk', 'RU', 63.20, 75.45, 1.16, 'onshore', 'medium', 4000000],
        ['russia', 'Kogalym', 'RU', 62.27, 74.48, 1.12, 'onshore', 'medium', 3800000],
        ['russia', 'Salym', 'RU', 60.06, 71.48, 1.09, 'onshore', 'medium', 3600000],
        ['russia', 'Baku North', 'AZ', 40.46, 49.87, 1.08, 'onshore', 'medium', 3500000],
        ['russia', 'Atyrau', 'KZ', 47.12, 51.88, 1.15, 'onshore', 'medium', 3850000],
        ['russia', 'Aktobe', 'KZ', 50.28, 57.17, 1.11, 'onshore', 'medium', 3700000],
        ['russia', 'Novy Urengoy', 'RU', 66.08, 76.63, 1.13, 'onshore', 'medium', 3950000],
        ['africa', 'Doba', 'TD', 8.65, 16.85, 0.72, 'onshore', 'starter', 1350000],
        ['africa', 'Sarir South', 'LY', 27.60, 22.54, 0.79, 'onshore', 'starter', 1550000],
        ['africa', 'Hassi Messaoud East', 'DZ', 31.68, 6.13, 0.81, 'onshore', 'starter', 1700000],
        ['africa', 'Mombasa North', 'KE', -3.88, 39.68, 0.68, 'onshore', 'starter', 1300000],
        ['africa', 'Soyo', 'AO', -6.13, 12.37, 0.75, 'onshore', 'starter', 1450000],
        ['africa', 'Port Harcourt East', 'NG', 4.82, 7.10, 0.80, 'onshore', 'starter', 1600000],
        ['africa', 'Fula', 'SS', 9.42, 28.02, 0.71, 'onshore', 'starter', 1250000],
        ['africa', 'Bredasdorp', 'ZA', -34.53, 20.04, 0.74, 'onshore', 'starter', 1500000],
        ['africa', 'Cabinda', 'AO', -5.56, 12.19, 1.13, 'offshore', 'medium', 4100000],
        ['africa', 'Bonny Coast', 'NG', 4.45, 7.17, 1.12, 'offshore', 'medium', 4050000],
        ['africa', 'Suez Gulf', 'EG', 28.47, 33.24, 1.16, 'offshore', 'medium', 4250000],
        ['africa', 'El Borma', 'TN', 31.69, 9.15, 1.09, 'onshore', 'medium', 3450000],
        ['africa', 'Jubilee Coast', 'GH', 4.53, -2.88, 1.15, 'offshore', 'medium', 4200000],
        ['africa', 'Pointe-Noire', 'CG', -4.78, 11.86, 1.11, 'offshore', 'medium', 3950000],
        ['africa', 'Benguela Shelf', 'AO', -12.50, 13.10, 1.14, 'offshore', 'medium', 4150000],
        ['africa', 'Kampala West', 'UG', 0.35, 31.10, 1.08, 'onshore', 'medium', 3200000],
        ['africa', 'Sirte Basin', 'LY', 29.10, 18.65, 1.17, 'onshore', 'medium', 3750000],
        ['usa_canada', 'Wichita Falls', 'US', 33.91, -98.49, 0.71, 'onshore', 'starter', 1700000],
        ['usa_canada', 'Bakersfield South', 'US', 35.34, -119.02, 0.78, 'onshore', 'starter', 1950000],
        ['usa_canada', 'Williston', 'US', 48.15, -103.63, 0.75, 'onshore', 'starter', 1800000],
        ['usa_canada', 'Medicine Hat', 'CA', 50.04, -110.68, 0.74, 'onshore', 'starter', 1750000],
        ['usa_canada', 'Leduc', 'CA', 53.26, -113.55, 0.82, 'onshore', 'starter', 2000000],
        ['usa_canada', 'Ardmore', 'US', 34.17, -97.14, 0.69, 'onshore', 'starter', 1550000],
        ['usa_canada', 'Casper', 'US', 42.85, -106.32, 0.76, 'onshore', 'starter', 1850000],
        ['usa_canada', 'Farmington', 'US', 36.73, -108.21, 0.73, 'onshore', 'starter', 1750000],
        ['usa_canada', 'Midland South', 'US', 31.93, -102.12, 1.17, 'onshore', 'medium', 4400000],
        ['usa_canada', 'Eagle Ford West', 'US', 28.84, -99.27, 1.12, 'onshore', 'medium', 4100000],
        ['usa_canada', 'Fort McMurray South', 'CA', 56.65, -111.22, 1.14, 'onshore', 'medium', 4300000],
        ['usa_canada', 'Prudhoe Bay East', 'US', 70.25, -148.35, 1.15, 'onshore', 'medium', 4500000],
        ['usa_canada', 'Denver Basin', 'US', 40.13, -104.65, 1.09, 'onshore', 'medium', 3900000],
        ['usa_canada', 'Peace River', 'CA', 56.23, -117.29, 1.10, 'onshore', 'medium', 3950000],
        ['usa_canada', 'Monterey Basin', 'US', 36.59, -121.89, 1.08, 'onshore', 'medium', 4000000],
        ['usa_canada', 'Marcellus West', 'US', 40.44, -80.00, 1.11, 'onshore', 'medium', 4050000],
        ['usa_canada', 'Saskatoon East', 'CA', 52.15, -106.58, 1.13, 'onshore', 'medium', 4000000],
        ['north_europe', 'Gorlice', 'PL', 49.66, 21.16, 0.69, 'onshore', 'starter', 1450000],
        ['north_europe', 'Krosno', 'PL', 49.69, 21.77, 0.72, 'onshore', 'starter', 1550000],
        ['north_europe', 'Celle', 'DE', 52.62, 10.08, 0.76, 'onshore', 'starter', 1750000],
        ['north_europe', 'Emlichheim', 'DE', 52.61, 6.85, 0.80, 'onshore', 'starter', 1850000],
        ['north_europe', 'Assen', 'NL', 52.99, 6.56, 0.74, 'onshore', 'starter', 1900000],
        ['north_europe', 'Esbjerg Coast', 'DK', 55.47, 8.45, 0.70, 'onshore', 'starter', 1750000],
        ['north_europe', 'Aberdeen South', 'GB', 57.08, -2.12, 0.78, 'onshore', 'starter', 1950000],
        ['north_europe', 'Stavanger East', 'NO', 58.97, 5.82, 0.73, 'onshore', 'starter', 1900000],
        ['north_europe', 'Ekofisk South', 'NO', 56.50, 3.26, 1.15, 'offshore', 'medium', 4500000],
        ['north_europe', 'Brent North', 'GB', 61.08, 1.72, 1.12, 'offshore', 'medium', 4450000],
        ['north_europe', 'Forties East', 'GB', 57.75, 1.07, 1.14, 'offshore', 'medium', 4400000],
        ['north_europe', 'Draugen West', 'NO', 64.35, 7.78, 1.17, 'offshore', 'medium', 4500000],
        ['north_europe', 'Statfjord South', 'NO', 61.18, 1.85, 1.16, 'offshore', 'medium', 4500000],
        ['north_europe', 'Schoonebeek', 'NL', 52.66, 6.88, 1.08, 'onshore', 'medium', 3850000],
        ['north_europe', 'Heide', 'DE', 54.20, 9.10, 1.09, 'onshore', 'medium', 3900000],
        ['north_europe', 'Jaslo', 'PL', 49.75, 21.47, 1.11, 'onshore', 'medium', 3600000],
        ['north_europe', 'Snorre East', 'NO', 61.45, 2.16, 1.13, 'offshore', 'medium', 4450000],
        ['southeast_asia', 'Riau North', 'ID', 1.10, 101.50, 0.73, 'onshore', 'starter', 1400000],
        ['southeast_asia', 'Jambi', 'ID', -1.61, 103.61, 0.78, 'onshore', 'starter', 1550000],
        ['southeast_asia', 'Balikpapan South', 'ID', -1.35, 116.85, 0.81, 'onshore', 'starter', 1700000],
        ['southeast_asia', 'Tarakan', 'ID', 3.30, 117.63, 0.75, 'onshore', 'starter', 1500000],
        ['southeast_asia', 'Miri', 'MY', 4.40, 114.01, 0.79, 'onshore', 'starter', 1750000],
        ['southeast_asia', 'Sibu', 'MY', 2.29, 111.83, 0.71, 'onshore', 'starter', 1450000],
        ['southeast_asia', 'Duri', 'ID', 1.27, 101.20, 0.77, 'onshore', 'starter', 1600000],
        ['southeast_asia', 'Dumai', 'ID', 1.67, 101.45, 0.74, 'onshore', 'starter', 1550000],
        ['southeast_asia', 'Cepu', 'ID', -7.15, 111.59, 1.11, 'onshore', 'medium', 3600000],
        ['southeast_asia', 'Minas East', 'ID', 0.75, 101.45, 1.15, 'onshore', 'medium', 3900000],
        ['southeast_asia', 'Brunei South', 'BN', 4.71, 114.99, 1.14, 'onshore', 'medium', 4000000],
        ['southeast_asia', 'Natuna Coast', 'ID', 3.94, 108.38, 1.16, 'offshore', 'medium', 4300000],
        ['southeast_asia', 'Kakap', 'ID', 3.89, 107.80, 1.12, 'offshore', 'medium', 4200000],
        ['southeast_asia', 'Palawan West', 'PH', 10.31, 119.20, 1.10, 'offshore', 'medium', 4100000],
        ['southeast_asia', 'Bach Ho South', 'VN', 9.72, 107.97, 1.13, 'offshore', 'medium', 4250000],
        ['southeast_asia', 'Songkhla Coast', 'TH', 7.20, 100.60, 1.08, 'offshore', 'medium', 4050000],
        ['southeast_asia', 'Sarawak Central', 'MY', 3.30, 113.12, 1.17, 'onshore', 'medium', 3950000],
        ['latam', 'Neuquen South', 'AR', -39.10, -68.10, 0.79, 'onshore', 'starter', 1600000],
        ['latam', 'Caleta Olivia', 'AR', -46.44, -67.53, 0.74, 'onshore', 'starter', 1400000],
        ['latam', 'Barrancabermeja', 'CO', 7.07, -73.85, 0.81, 'onshore', 'starter', 1750000],
        ['latam', 'Villavicencio', 'CO', 4.15, -73.64, 0.76, 'onshore', 'starter', 1550000],
        ['latam', 'Talara', 'PE', -4.58, -81.27, 0.72, 'onshore', 'starter', 1500000],
        ['latam', 'Pucallpa', 'PE', -8.38, -74.55, 0.77, 'onshore', 'starter', 1450000],
        ['latam', 'Maturin', 'VE', 9.75, -63.18, 0.80, 'onshore', 'starter', 1550000],
        ['latam', 'Santa Cruz East', 'BO', -17.78, -63.12, 0.71, 'onshore', 'starter', 1350000],
        ['latam', 'Vaca Muerta West', 'AR', -38.55, -69.40, 1.17, 'onshore', 'medium', 3900000],
        ['latam', 'Llanos Basin', 'CO', 4.45, -72.55, 1.12, 'onshore', 'medium', 3700000],
        ['latam', 'Mara Valley', 'VE', 10.92, -71.73, 1.15, 'onshore', 'medium', 3600000],
        ['latam', 'Campos South', 'BR', -22.75, -40.10, 1.16, 'offshore', 'medium', 4250000],
        ['latam', 'Santos East', 'BR', -24.40, -43.70, 1.17, 'offshore', 'medium', 4400000],
        ['latam', 'Tierra del Fuego', 'AR', -54.80, -68.30, 1.09, 'onshore', 'medium', 3450000],
        ['latam', 'Oriente Basin', 'EC', -0.77, -76.95, 1.13, 'onshore', 'medium', 3650000],
        ['latam', 'Madre de Dios', 'PE', -11.95, -69.60, 1.08, 'onshore', 'medium', 3400000],
        ['latam', 'Lake Maracaibo East', 'VE', 9.82, -71.55, 1.14, 'onshore', 'medium', 3550000],
    ];

    public function __construct(private PDO $db)
    {
    }

    public function seed(): void
    {
        $check = $this->db->prepare('SELECT 1 FROM well_config WHERE `key` = ? LIMIT 1');
        $check->execute([self::MARKER]);
        if ($check->fetchColumn()) {
            return;
        }

        $mysql = $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
        if ($mysql) {
            $lock = $this->db->prepare('SELECT GET_LOCK(?, 5)');
            $lock->execute([self::LOCK]);
            if ((int) $lock->fetchColumn() !== 1) {
                throw new RuntimeException('Location catalog lock unavailable');
            }
        }

        $ownsTransaction = !$this->db->inTransaction();
        try {
            if ($ownsTransaction) {
                $this->db->beginTransaction();
            }
            $recheck = $this->db->prepare('SELECT 1 FROM well_config WHERE `key` = ? LIMIT 1');
            $recheck->execute([self::MARKER]);
            if (!$recheck->fetchColumn()) {
                $regions = $this->db->query('SELECT id, code FROM world_regions')->fetchAll(PDO::FETCH_KEY_PAIR);
                $regions = array_flip($regions);
                $existing = [];
                foreach ($this->db->query('SELECT region_id, name FROM world_locations') as $row) {
                    $existing[(int) $row['region_id']][$row['name']] = true;
                }
                $insert = $this->db->prepare(
                    'INSERT INTO world_locations (region_id, name, country_code, latitude, longitude, oil_richness, well_type, tier, entry_cost_override, available)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)'
                );
                foreach (self::LOCATIONS as [$code, $name, $country, $lat, $lon, $richness, $type, $tier, $cost]) {
                    if (!isset($regions[$code])) {
                        throw new RuntimeException('Missing world region: ' . $code);
                    }
                    $regionId = (int) $regions[$code];
                    if (!isset($existing[$regionId][$name])) {
                        $insert->execute([$regionId, $name, $country, $lat, $lon, $richness, $type, $tier, $cost]);
                        $existing[$regionId][$name] = true;
                    }
                }
                $marker = $this->db->prepare(
                    "INSERT INTO well_config (`key`, `value`, label, category) VALUES (?, 1, 'World locations catalog', 'system')"
                );
                $marker->execute([self::MARKER]);
            }
            if ($ownsTransaction) {
                $this->db->commit();
            }
        } catch (Throwable $e) {
            if ($ownsTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        } finally {
            if ($mysql) {
                $release = $this->db->prepare('SELECT RELEASE_LOCK(?)');
                $release->execute([self::LOCK]);
            }
        }
    }
}
