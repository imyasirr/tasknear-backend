<?php

return [
    /*
    | Who can accept a booking. slug = service_requests.provider_type = user_roles.role
    | match_mode: vendor = company ring (VendorOffer), worker = individual ring (Assignment)
    */
    'provider_types' => [
        [
            'slug' => 'caterer',
            'role' => 'caterer',
            'match_mode' => 'vendor',
            'name' => 'Catering company',
            'name_hi' => 'कैटरिंग कंपनी',
            'description' => 'Verified company sends waiters, helpers and event crew.',
            'description_hi' => 'वेरिफ़ाइड कंपनी वेटर, हेल्पर और इवेंट क्रू भेजती है।',
            'active' => true,
        ],
        [
            'slug' => 'agency',
            'role' => 'agency',
            'match_mode' => 'vendor',
            'name' => 'Manpower agency',
            'name_hi' => 'मnpower एजेंसी',
            'description' => 'Agency supplies teams for events, loading and on-site work.',
            'description_hi' => 'एजेंसी इवेंट, लोडिंग और ऑन-साइट काम के लिए टीम भेजती है।',
            'active' => true,
        ],
        [
            'slug' => 'worker',
            'role' => 'worker',
            'match_mode' => 'worker',
            'name' => 'Individual worker',
            'name_hi' => 'अलग वर्कर',
            'description' => 'Helper, loader or general task — direct accept from app.',
            'description_hi' => 'हेल्पर, लोडर या जनरल टास्क — ऐप से सीधे स्वीकार।',
            'active' => true,
        ],
        [
            'slug' => 'driver',
            'role' => 'driver',
            'match_mode' => 'worker',
            'name' => 'Driver partner',
            'name_hi' => 'ड्राइवर पार्टनर',
            'description' => 'Verified drivers for shifting, delivery and pickup runs.',
            'description_hi' => 'शिफ्टिंग, डिलीवरी और पिकअप के लिए वेरिफ़ाइड ड्राइवर।',
            'active' => true,
        ],
        [
            'slug' => 'home_pro',
            'role' => 'home_pro',
            'match_mode' => 'worker',
            'name' => 'Home services pro',
            'name_hi' => 'घर की सेवा विशेषज्ञ',
            'description' => 'Electrician, plumber and home repair professionals.',
            'description_hi' => 'इलेक्ट्रीशियन, प्लंबर और घर की मरम्मत के प्रोफ़ेशनल।',
            'active' => true,
        ],
    ],
];
