<?php

namespace App\Enums;

enum AttendanceType : string
{
    Case NONE = 'none';

    Case GEOFENCE = 'geofence';

    Case STATIC_QR = 'static_qr';

    Case DYNAMIC_QR = 'dynamic_qr';

    Case IP_ADDRESS = 'ip_address';

    Case FACE_RECOGNITION = 'face_recognition';

    Case FINGERPRINT = 'fingerprint';

    Case NFC = 'nfc';

    Case RFID = 'rfid';

    Case MANUAL = 'manual';

    Case SITE = 'site';
}
