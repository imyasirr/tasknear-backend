<?php

return [
    'upload' => [
        'image_max_kb' => 20480, // 20 MB — high-quality photos
        'video_max_kb' => 81920, // 80 MB — venue walkthrough clips
        'image_mimes' => ['jpg', 'jpeg', 'png', 'webp'],
        'video_mimes' => ['mp4', 'webm', 'mov', 'quicktime'],
    ],

    'amenity_categories' => [
        'space' => ['en' => 'Space & rooms', 'hi' => 'जगह और कमरे'],
        'parking' => ['en' => 'Parking', 'hi' => 'पार्किंग'],
        'comfort' => ['en' => 'Comfort & setup', 'hi' => 'आराम और सेटअप'],
        'services' => ['en' => 'Services & extras', 'hi' => 'सेवाएँ और अतिरिक्त'],
        'custom' => ['en' => 'Custom services', 'hi' => 'आपकी खुद की सेवाएँ'],
    ],

    'amenity_fields' => [
        // Space
        'halls' => ['type' => 'count', 'category' => 'space', 'en' => 'Halls', 'hi' => 'हॉल'],
        'rooms' => ['type' => 'count', 'category' => 'space', 'en' => 'Rooms', 'hi' => 'कमरे'],
        'bathrooms' => ['type' => 'count', 'category' => 'space', 'en' => 'Bathrooms', 'hi' => 'बाथरूम'],
        'changing_rooms' => ['type' => 'count', 'category' => 'space', 'en' => 'Changing rooms', 'hi' => 'चेंजिंग रूम'],
        'bridal_room' => ['type' => 'bool', 'category' => 'space', 'en' => 'Bridal / green room', 'hi' => 'ब्राइडल / ग्रीन रूम'],

        // Parking
        'parking_cars' => ['type' => 'count', 'category' => 'parking', 'en' => 'Car parking', 'hi' => 'कार पार्किंग'],
        'parking_bikes' => ['type' => 'count', 'category' => 'parking', 'en' => 'Bike parking', 'hi' => 'बाइक पार्किंग'],
        'valet' => ['type' => 'bool', 'category' => 'parking', 'en' => 'Valet parking', 'hi' => 'वैलेट पार्किंग'],

        // Comfort
        'ac' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Air conditioning', 'hi' => 'एसी'],
        'wifi' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Wi‑Fi', 'hi' => 'Wi‑Fi'],
        'generator' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Power backup', 'hi' => 'पावर बैकअप'],
        'stage' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Stage', 'hi' => 'स्टेज'],
        'kitchen' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Kitchen', 'hi' => 'किचन'],
        'garden' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Lawn / garden', 'hi' => 'लawn / बगीचा'],
        'lift' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Lift / elevator', 'hi' => 'लिफ्ट'],
        'wheelchair_access' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Wheelchair access', 'hi' => 'व्हीलचेयर एक्सेस'],
        'prayer_room' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Prayer room', 'hi' => 'प्रार्थना कक्ष'],
        'swimming_pool' => ['type' => 'bool', 'category' => 'comfort', 'en' => 'Swimming pool', 'hi' => 'स्विमिंग पूल'],

        // Services
        'catering' => ['type' => 'bool', 'category' => 'services', 'en' => 'In-house catering', 'hi' => 'इन-हाउस कैटरिंग'],
        'decoration' => ['type' => 'bool', 'category' => 'services', 'en' => 'Decoration', 'hi' => 'सजावट / डेकोरेशन'],
        'sound_system' => ['type' => 'bool', 'category' => 'services', 'en' => 'Sound system', 'hi' => 'साउंड सिस्टम'],
        'dj' => ['type' => 'bool', 'category' => 'services', 'en' => 'DJ / music', 'hi' => 'DJ / म्यूज़िक'],
        'photography' => ['type' => 'bool', 'category' => 'services', 'en' => 'Photography', 'hi' => 'फोटोग्राफी'],
        'videography' => ['type' => 'bool', 'category' => 'services', 'en' => 'Videography', 'hi' => 'वीडियोग्राफी'],
        'security' => ['type' => 'bool', 'category' => 'services', 'en' => 'Security staff', 'hi' => 'सुरक्षा कर्मी'],
        'bar' => ['type' => 'bool', 'category' => 'services', 'en' => 'Bar / beverages', 'hi' => 'बार / पेय'],
        'dry_cleaning' => ['type' => 'bool', 'category' => 'services', 'en' => 'Dry cleaning', 'hi' => 'ड्राई क्लीनिंग'],
        'laundry' => ['type' => 'bool', 'category' => 'services', 'en' => 'Laundry', 'hi' => 'लॉन्ड्री'],
    ],

    'venue_types' => [
        'lawn' => ['en' => 'Lawn', 'hi' => 'लawn'],
        'banquet' => ['en' => 'Banquet hall', 'hi' => 'बैंक्वेट हॉल'],
        'both' => ['en' => 'Lawn + banquet', 'hi' => 'लawn + बैंक्वेट'],
    ],

    /** Icon keys stored on custom_services rows (must match frontend SERVICE_ICON_OPTIONS). */
    'service_icon_keys' => [
        'halls', 'rooms', 'bathrooms', 'changing_rooms', 'bridal_room',
        'parking_cars', 'parking_bikes', 'valet',
        'ac', 'wifi', 'generator', 'stage', 'kitchen', 'garden', 'lift', 'wheelchair_access', 'prayer_room', 'swimming_pool',
        'catering', 'decoration', 'sound_system', 'dj', 'photography', 'videography', 'security', 'bar', 'dry_cleaning', 'laundry',
        'bus', 'gift', 'music', 'flower', 'sparkle', 'star', 'check',
    ],
];
