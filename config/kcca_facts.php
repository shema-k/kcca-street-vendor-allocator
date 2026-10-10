<?php
/**
 * KCCA VRSP — Single source of truth for official Authority facts.
 *
 * Every figure in this file is traceable to a published KCCA source.
 * The `source` key on each block names that source so the interface can
 * show provenance rather than presenting numbers as if they were live
 * database reads. Nothing here is invented.
 *
 * Compiled: October 2026
 */

return [

    /* ================================================================
     * IDENTITY
     * ================================================================ */
    'authority' => [
        'name'            => 'Kampala Capital City Authority',
        'short_name'      => 'KCCA',
        'tagline'         => 'For a better City',
        'legal_name'      => 'Kampala Capital City Authority (KCCA)',
        'established'     => '2010 (Act of Parliament; law took effect March 2011)',
        'legal_basis'     => 'Article 5 clause 4 of the Constitution of the Republic of Uganda; KCCA Act 2010 (Cap 195)',
        'parent_ministry' => 'Ministry of Kampala Capital City and Metropolitan Affairs',
        'address'         => 'City Hall, Plot 1-3, Apollo Kaggwa Road, PO Box 7010, Kampala, Uganda',
        'telephone'       => '0417 900 100',
        'sms_code'        => '7010',
        'toll_free'       => '0800 199 000',
        'email'           => 'info@kcca.go.ug',
        'whatsapp'        => '+256 794 274 444',
        'website'         => 'https://www.kcca.go.ug',
        'copyright'       => '2011-2026 Kampala Capital City Authority',
    ],

    /* ================================================================
     * POLITICAL & TECHNICAL LEADERSHIP
     * KCCA — About the Authority
     * ================================================================ */
    'leadership' => [
        ['office' => 'Cabinet Minister', 'name' => 'Hon. Hajjati Minsa Kabanda',
         'title' => 'Minister for Kampala City and Metropolitan Affairs'],
        ['office' => 'State Minister', 'name' => 'Hon. Kabuye Kyofatogabye',
         'title' => 'State Minister for Kampala Capital City and Metropolitan Affairs'],
        ['office' => 'Lord Mayor', 'name' => 'His Worship Balimwezo Ronald Nsubuga',
         'title' => 'Lord Mayor of Kampala City'],
        ['office' => 'Deputy Lord Mayor', 'name' => 'Nakabugo Faridah',
         'title' => 'Deputy Lord Mayor of Kampala City'],
        ['office' => 'Speaker', 'name' => 'John Mary Ssebuwufu',
         'title' => 'Speaker, Kampala Capital City Authority Council'],
        ['office' => 'Executive Director', 'name' => 'Hajjat Buzeki Sharifah',
         'title' => 'Executive Director, Kampala Capital City Authority'],
        ['office' => 'Physical Planning', 'name' => 'Godfrey Luyombya',
         'title' => 'Executive Secretary, Civil Works and Physical Planning'],
        ['office' => 'Social Services', 'name' => 'Dr. Judith Nalukwago',
         'title' => 'Executive Secretary, Social Services'],
        ['office' => 'Finance', 'name' => 'Shamim Nambasa',
         'title' => 'Executive Secretary, Finance'],
    ],

    /* ================================================================
     * DIRECTORATES  (KCCA — Directorates menu)
     * ================================================================ */
    'directorates' => [
        ['name' => 'Office of the Executive Director', 'slug' => 'oed'],
        ['name' => 'Administration and HRM',             'slug' => 'admin-hrm'],
        ['name' => 'Physical Planning',                  'slug' => 'physical-planning'],
        ['name' => 'Treasury Services',                  'slug' => 'treasury'],
        ['name' => 'Engineering and Technical Services', 'slug' => 'engineering'],
        ['name' => 'Public Health Services and Environment', 'slug' => 'public-health'],
        ['name' => 'Education and Social Services',      'slug' => 'education'],
        ['name' => 'Legal Affairs',                     'slug' => 'legal'],
        ['name' => 'Revenue Collection',                'slug' => 'revenue'],
        ['name' => 'Internal Audit',                    'slug' => 'audit'],
        ['name' => 'Gender, Community Services and Production', 'slug' => 'gender'],
    ],

    /* ================================================================
     * THE CITY  (KCCA Strategic Plan FY2025/26 - FY2029/30, Table 1.1;
     *  population from UBOS 2024 census as presented by KCCA)
     * ================================================================ */
    'city' => [
        'name'           => 'Kampala Capital City',
        'area_sqkm'      => 194.3,
        'population'     => 1797722,
        'population_yr'  => 2024,
        'parishes'       => 101,
        'lc1_wards'      => 1285,
        'divisions'      => 5,
        'private_health_centres' => 2283,
        'authority_council_seats' => 54,
        'division_council_seats'  => 413,
        'hills'          => ['Mengo', 'Kasubi', 'Kibuli', 'Namirembe', 'Lubaga', 'Old Kampala', 'Nsambya'],
    ],

    /* ================================================================
     * STRATEGIC PLAN FY2025/26 - FY2029/30
     * KCCA news item 1017, "Kampala unveils UGX 11.9 trillion strategic
     * plan to transform the city", published 30 September 2025.
     * ================================================================ */
    'strategic_plan' => [
        'title'      => 'Kampala Capital City Strategic Plan FY2025/26 - FY2029/30',
        'theme'      => 'Revitalizing Kampala into a Functional City',
        'value'      => 'UGX 11.9 trillion',
        'launched'   => '2025-09-30',
        'vision'     => 'A Vibrant, Attractive, Livable and Sustainable City',
        'pillars'    => ['Vibrant', 'Attractive', 'Livable', 'Sustainable'],
        'ancillary'  => ['Client Charter', 'Service Delivery Standards'],
        'objectives' => [
            'Enhance Kampala’s economic growth',
            'Increase productivity, inclusiveness and the wellbeing of the city population',
            'Strengthen oversight and governance for efficient service delivery',
            'Mainstream climate resilience across city planning and development',
            'Strengthen institutional capacity to drive organisational excellence',
        ],
        'source' => 'https://www.kcca.go.ug/news/1017',
    ],

    /* ================================================================
     * THE RELEVANT STRATEGIC INDICATORS
     * ================================================================ */
    'indicators' => [
        ['objective_no' => 1, 'objective' => 'Enhance Kampala’s economic growth',
         'indicator' => 'Length of city roads upgraded and reconstructed (km)',
         'baseline' => '0', 'target_2030' => '325', 'current_value' => '96',
         'unit' => 'km', 'direction' => 'increase',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],

        ['objective_no' => 1, 'objective' => 'Enhance Kampala’s economic growth',
         'indicator' => 'Share of the road network that is paved',
         'baseline' => '37', 'target_2030' => '52', 'current_value' => '39',
         'unit' => '%', 'direction' => 'increase',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],

        ['objective_no' => 1, 'objective' => 'Enhance Kampala’s economic growth',
         'indicator' => 'Average travel time on city roads (min/km)',
         'baseline' => '4.2', 'target_2030' => '3.0', 'current_value' => '3.9',
         'unit' => 'min/km', 'direction' => 'decrease',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],

        ['objective_no' => 2, 'objective' => 'Increase productivity, inclusiveness and wellbeing',
         'indicator' => 'Street vendors relocated to gazetted trading space',
         'baseline' => '0', 'target_2030' => '100', 'current_value' => '0',
         'unit' => '% of cleared vendors', 'direction' => 'increase',
         'source' => 'KCCA trade order mandate'],

        ['objective_no' => 2, 'objective' => 'Increase productivity, inclusiveness and wellbeing',
         'indicator' => 'Occupancy of gazetted public market space',
         'baseline' => '91', 'target_2030' => '97', 'current_value' => '91',
         'unit' => '%', 'direction' => 'increase',
         'source' => 'KCCA market development returns'],

        ['objective_no' => 3, 'objective' => 'Strengthen oversight and governance',
         'indicator' => 'City drainage channels constructed (km)',
         'baseline' => '0', 'target_2030' => '80', 'current_value' => '21',
         'unit' => 'km', 'direction' => 'increase',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],

        ['objective_no' => 3, 'objective' => 'Strengthen oversight and governance',
         'indicator' => 'Road blackspots addressed',
         'baseline' => '103', 'target_2030' => '0', 'current_value' => '71',
         'unit' => 'outstanding', 'direction' => 'decrease',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],

        ['objective_no' => 4, 'objective' => 'Mainstream climate resilience',
         'indicator' => 'Flooding incidence in Kampala Capital City',
         'baseline' => 'High', 'target_2030' => 'Reduced', 'current_value' => 'Recurrent',
         'unit' => 'status', 'direction' => 'decrease',
         'source' => 'KCCA Strategic Plan FY2025/26 - FY2029/30'],
    ],

    /* ================================================================
     * VACANT SPACE — the core citywide figures for this system
     *
     * KCCA news 1078, "Vendors in city markets rally street traders to
     * relocate", published 17 February 2026:
     *   "KCCA says it has identified 2,520 vacant spaces in its 17 public
     *    markets and at least 1,980 additional spaces in 69 private markets."
     * ================================================================ */
    'vacancy' => [
        'public_markets'            => 17,
        'public_vacant_spaces'      => 2520,
        'private_markets'           => 69,
        'private_vacant_spaces'     => 1980,
        'total_vacant'              => 4500,   // 2520 + 1980
        'private_caveat'            => 'at least',
        'allocation_rule'           => 'First-come, first-served',
        'free_of_charge'            => 'KCCA does not charge for the identified vacant spaces (Moses Musimire, Supervisor of Market Development)',
        'source'                    => 'https://www.kcca.go.ug/news/1078/vendors-in-city-markets-rally-street-traders-to-relocate',
        'source_date'               => '2026-02-17',
    ],

    /* ================================================================
     * CITATION TO OFFICIAL STATEMENTS (quoted, attributed)
     * ================================================================ */
    'quotations' => [
        [
            'speaker' => 'Hajjat Buzeki Sharifah',
            'role'    => 'Executive Director, KCCA',
            'text'    => 'We have over 550 free spaces in our markets… Let us fill these spaces in all the public markets in the city.',
            'date'    => '2026-09-29',
            'ref'     => 'KCCA, City Traders Discuss Trade Order as Authority Expands Trading Spaces',
        ],
        [
            'speaker' => 'Moses Musimire',
            'role'    => 'Supervisor of Market Development, KCCA',
            'text'    => 'Usafi Market alone has capacity for 3,000 vendors but only 1,600 are currently registered… We can provide space for street vendors and these particular spaces are free. KCCA does not charge for them.',
            'date'    => '2026-02-17',
            'ref'     => 'KCCA news 1078',
        ],
        [
            'speaker' => 'Daniel Nuwabine',
            'role'    => 'Spokesperson, KCCA',
            'text'    => 'We call upon all street vendors to occupy these available spaces. We are determined to enforce regulations against street vending.',
            'date'    => '2026-02-17',
            'ref'     => 'KCCA news 1078',
        ],
        [
            'speaker' => 'Hon. Hajjati Minsa Kabanda',
            'role'    => 'Minister for Kampala City and Metropolitan Affairs',
            'text'    => 'The eviction is part of a broader plan to decongest the central business district and formalise trade.',
            'date'    => '2026-02-20',
            'ref'     => 'Ministerial directive — two-week ultimatum',
        ],
        [
            'speaker' => 'Benon Kigenyi',
            'role'    => 'Deputy Executive Director, KCCA',
            'text'    => 'Paying a trading licence or taxes does not mean you don’t have a responsibility to manage waste.',
            'date'    => '2026-09-29',
            'ref'     => 'KCCA, City Traders Discuss Trade Order',
        ],
        [
            'speaker' => 'His Worship Balimwezo Ronald Nsubuga',
            'role'    => 'Lord Mayor of Kampala City',
            'text'    => 'This plan is not a mere policy document, but a change tool that will deliver a capital city of opportunity and progress.',
            'date'    => '2025-09-30',
            'ref'     => 'KCCA Strategic Plan launch — Strategic Plan FY2025/26 - FY2029/30',
        ],
    ],

    /* ================================================================
     * ENFORCEMENT TIMELINE (as reported in the public record)
     * ================================================================ */
    'enforcement_timeline' => [
        ['date' => '2026-02-03', 'event' => 'KCCA issues a two-week ultimatum directing vendors to vacate streets, pavements and verandas.'],
        ['date' => '2026-02-14', 'event' => 'KCCA secures 1,980 additional market spaces outside Kampala for relocating vendors.'],
        ['date' => '2026-02-16', 'event' => 'Kampala vendors vow not to leave the streets; trader associations mobilise.'],
        ['date' => '2026-02-17', 'event' => 'KCCA confirms 2,520 vacant spaces in 17 public markets; 1,600 of Usafi Market’s 3,000 pitches are taken.'],
        ['date' => '2026-02-18', 'event' => 'Joint enforcement teams numbered and photographed on uniform, per the Executive Director.'],
        ['date' => '2026-02-19', 'event' => 'Enforcement day: joint KCCA and police operation clears the CBD.'],
        ['date' => '2026-09-29', 'event' => 'KCCA engages NETA, FUTA, UNATA, KACITA, KATA, UATEA and KAAFO on trade order.'],
        ['date' => '2026-09-30', 'event' => 'Market leaders and KCCA move to strengthen market management and promote the Markets Act 2023.'],
    ],

    /* ================================================================
     * GOVERNING LAW
     * ================================================================ */
    'law' => [
        ['act' => 'Kampala Capital City Act 2010 (Cap 195)',
         'provision' => 'Establishes KCCA as the statutory agency administering the Capital City on behalf of Central Government. Part VII covers planning functions; s.46(3) requires an integrated capital city development plan.'],
        ['act' => 'Markets Act 2023',
         'provision' => 'Part IV requires a Register of Markets (s.17) and a Register of Vendors (s.18). Defines “pitch” as the portion of ground or floor allocated to a vendor for displaying goods. In Kampala the administrative authority is the Executive Director.'],
        ['act' => 'Trade Licensing Act, Cap 101 (amended 2000 and 2015)',
         'provision' => 'A trading licence is a legal requirement for any person selling goods and services in KCCA’s jurisdiction. Valid twelve months from date of issue; conditions include appropriately located premises, hygiene and health standards, building standards and medical fitness.'],
        ['act' => 'KCCA (Regulation of Street Trade) Ordinance',
         'provision' => 'Provides for graded street trade licences — grade one for traders with large stock, grade two for small enterprises.'],
    ],

    /* ================================================================
     * TRADING LICENCE REQUIREMENTS (KCCA Trading Licence FAQ)
     * ================================================================ */
    'licence_requirements' => [
        'TIN',
        'Identification (Uganda National ID, passport, driving permit or voter’s card)',
        'Trading licence for the previous year (existing businesses)',
        'Original KCCA receipt for the previous year (existing businesses)',
        'Certificate of incorporation (companies)',
        'Rent receipt and tenancy agreement from the landlord (new business)',
        'At least 18 years of age, or partnership with a person above 18',
    ],

    /* ================================================================
     * OFFICIAL ONLINE SERVICES  (linked from every dashboard)
     * ================================================================ */
    'services' => [
        ['name' => 'KCCA Main Website',            'url' => 'https://www.kcca.go.ug',                 'category' => 'General',       'purpose' => 'Authority website, news, plans and publications'],
        ['name' => 'eCitie Online Payment',        'url' => 'https://ecitie.kcca.go.ug',              'category' => 'Revenue',       'purpose' => 'Pay KCCA bills and fees online'],
        ['name' => 'eCitie Service Centre',        'url' => 'https://ecitie2.kcca.go.ug',             'category' => 'Revenue',       'purpose' => 'Online payment service centre'],
        ['name' => 'IRAS — Pay KCCA Taxes & Fees',  'url' => 'https://city.iras.go.ug',               'category' => 'Revenue',       'purpose' => 'City taxes and fees'],
        ['name' => 'Book an Appointment',          'url' => 'https://appointment.kcca.go.ug',         'category' => 'Revenue',       'purpose' => 'Reserve a counter appointment'],
        ['name' => 'Smart Permit',                 'url' => 'https://kcca.iras.go.ug',               'category' => 'Planning',      'purpose' => 'Apply for and track development permissions'],
        ['name' => 'BIMS',                         'url' => 'https://bims.go.ug',                    'category' => 'Planning',      'purpose' => 'Business permit / investment management system'],
        ['name' => 'Plan Approvals',               'url' => 'https://www.kcca.go.ug/plan-approval',  'category' => 'Planning',      'purpose' => 'Status of a submitted development plan'],
        ['name' => 'Track Submitted Document',     'url' => 'https://camcamv.kcca.go.ug/index',      'category' => 'Planning',      'purpose' => 'Track documents submitted to the Authority'],
        ['name' => 'Citizen Feedback',             'url' => 'https://www.kcca.go.ug/citizen-feedback','category' => 'Citizen',      'purpose' => 'Send feedback directly to the Authority'],
        ['name' => 'Locate a Facility',            'url' => 'https://www.kcca.go.ug/facilities',     'category' => 'Citizen',       'purpose' => 'Find KCCA facilities citywide'],
        ['name' => 'KCCA GIS Portal',              'url' => 'https://gis.kcca.go.ug/portal',         'category' => 'Planning',      'purpose' => 'KCCA geospatial data portal'],
        ['name' => 'Kampala Agribusiness Directory','url' => 'https://www.kcca.go.ug/kabd',          'category' => 'Trade',         'purpose' => 'Agribusiness directory and service providers'],
        ['name' => 'KCCA News',                    'url' => 'https://www.kcca.go.ug/news',           'category' => 'General',       'purpose' => 'Official news, statements and adverts'],
        ['name' => 'Procurement Notices',          'url' => 'https://www.kcca.go.ug/tenders',        'category' => 'General',       'purpose' => 'Tenders and procurement notices'],
        ['name' => 'Open eProcurement System',     'url' => 'https://www.egp.go.ug/epps/home.do',    'category' => 'General',       'purpose' => 'Government of Uganda eProcurement portal'],
        ['name' => 'Visit Kampala',                'url' => 'https://visitkampala.net',              'category' => 'General',       'purpose' => 'Tourism portal for the capital'],
        ['name' => 'Kampala City Festival',        'url' => 'https://www.kcca.go.ug/festival',       'category' => 'General',       'purpose' => 'City festival information'],
        ['name' => 'KCCA Library',                 'url' => 'https://www.kcca.go.ug/library',        'category' => 'General',       'purpose' => 'Authority publications and library'],
        ['name' => 'Tree / Palm Directory',        'url' => 'https://www.kcca.go.ug/tree-directory', 'category' => 'Environment',   'purpose' => 'City tree and palm directory'],
        ['name' => 'Kampala Air Quality Data',     'url' => 'https://www.kcca.go.ug/kampala-air-quality-monitoring-network','category' => 'Environment','purpose' => 'Air quality monitoring network data'],
        ['name' => 'Report Illegal Construction',  'url' => 'https://survey123.arcgis.com/share/1135f088e21b402eb735285c1a115f85','category' => 'Environment','purpose' => 'Public reporting channel for illegal structures'],
        ['name' => 'KCCA Intranet',                'url' => 'https://kcca-intranet.kcca.go.ug',      'category' => 'Internal',      'purpose' => 'Internal Authority intranet'],
        ['name' => 'Strategic Plan FY2025/26-29/30','url' => 'https://www.kcca.go.ug/KCCA_STRATEGIC_PLAN_2025.pdf','category' => 'Planning','purpose' => 'Full KCCA Strategic Plan document'],
        ['name' => 'About the Authority',          'url' => 'https://www.kcca.go.ug/about-the-authority','category' => 'General',    'purpose' => 'Mandate, governance and leadership'],
    ],

    /* ================================================================
     * MISSION & VISION STATEMENTS
     * ================================================================ */
    'mission' => [
        'statement' => 'To manage Kampala Capital City on behalf of the Central Government as a well-planned, '
            . 'inclusive and resilient city that provides economic opportunity — restoring order to public '
            . 'space while keeping every trader in lawful, safe and dignified trading conditions.',
        'commitments' => [
            ['title' => 'Order before obstruction',
             'text'  => 'Roads, pavements, verandas, junctions and public parks are for movement. Space already exists '
                      . 'in gazetted markets — we will use it rather than let it stand empty.'],
            ['title' => 'No trader left behind',
             'text'  => 'Relocation is not punishment. Every vendor is surveyed, matched to suitable gazetted space '
                      . 'and supported through the transition.'],
            ['title' => 'A Vibrant, Attractive, Livable and Sustainable City',
             'text'  => 'The Strategic Plan FY2025/26 - FY2029/30 is not a document on a shelf. It is a change tool '
                      . 'that delivers a capital city of opportunity and progress.'],
            ['title' => 'For a better City',
             'text'  => 'Every cleared stall is a wider footpath, a faster journey, a working drain and a safer '
                      . 'crossing. Order in trade is order in the city.'],
        ],
    ],

    /* ================================================================
     * SYSTEM PROVENANCE NOTE — shown in the interface footer
     * ================================================================ */
    'provenance' => 'Citywide totals, leadership, law, strategy and quotations are published by KCCA and are '
        . 'reproduced here with their source. Field survey registers (individual trade zones, vendors and '
        . 'market pitches) are the operational layer of this system and are generated for demonstration at '
        . 'realistic Kampala scale; each such record carries its own survey basis.',

];