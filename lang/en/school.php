<?php

return [
    'not_found' => [
        'title' => 'School not found',
        'message' => 'We could not find a school at this address. Check the link you were given.',
    ],
    'unavailable' => [
        'title' => 'School unavailable',
        'message' => 'This school is not available right now. Please contact your school administrator.',
    ],
    'suspended' => [
        'title' => 'Subscription suspended',
        'message' => ':school’s account has been suspended by the platform. School data is safe, but staff, parents and students can’t use the app until the subscription is reactivated.',
        'reason' => 'Reason: :reason',
        'contact' => 'Contact billing',
    ],
    'expired' => [
        'title' => 'Subscription expired',
        'message' => 'The subscription for :school has expired. Renew it to restore access for staff and parents.',
        'contact' => 'Renew subscription',
    ],
];
