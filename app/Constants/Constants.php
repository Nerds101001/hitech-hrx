<?php

namespace App\Constants;

class Constants
{
  public const DateTimeFormat = 'd-m-Y h:i A';
  public const DateFormat = 'd-m-Y';
  public const TimeFormat = 'h:i A';
  public const DateTimeHumanFormat = 'F j, Y, g:i a';
  public const DateTimeHumanFormatShort = 'M d, Y H:i';
  public const BaseFolderVisitImages = 'uploads/visitimages/';
  public const BaseFolderTaskUpdateFiles = 'uploads/taskupdatefiles/';
  public const BaseFolderLeaveRequestDocument = 'uploads/leaverequestdocuments/';
  public const BaseFolderExpenseProofs = 'uploads/expenseProofs/';
  public const BaseFolderUserDocumentRequest = 'uploads/userDocumentRequests/';
  public const BaseFolderEmployeeProfile = 'uploads/employeeProfilePictures';
  public const BaseFolderEmployeeProfileWithSlash = 'uploads/employeeProfilePictures/';
  public const BaseFolderEmployeeDocument = 'uploads/employeeDocuments/';
  public const BaseFolderOnboardingDocuments = 'uploads/onboardingDocuments/';
  public const BaseFolderChatFiles = 'uploads/chatFiles/';
  public const ALL_ADDONS_PURCHASE_LINK = 'https://hitechhrx.com/laravel-addons/';
  public const All_ADDONS_ARRAY = [
    'LMS' => [
      'name' => 'Learning Management System',
      'description' => 'This module is used to manage learning management system.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'Assets' => [
      'name' => 'Assets',
      'description' => 'This module is used to manage assets for employees.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'Calendar' => [
      'name' => 'Calendar',
      'description' => 'This module is used to manage calendar events.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'Notes' => [
      'name' => 'Notes',
      'description' => 'This module is used to manage notes for employees.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'Recruitment' => [
      'name' => 'Recruitment',
      'description' => 'This module is used to manage recruitment process.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'BreakSystem' => [
      'name' => 'Break System',
      'description' => 'This module is used to manage breaks for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/break-system-addon-laravel/',
    ],
    'DataImportExport' => [
      'name' => 'Data Import Export',
      'description' => 'This module is used to import and export data from the system.',
      'purchase_link' => 'https://hitechhrx.com/product/data-import-export-addon/',
    ],
    'DocumentManagement' => [
      'name' => 'Document Management',
      'description' => 'This module is used to manage document request for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/document-request-addon-laravel/',
    ],
    'DynamicForms' => [
      'name' => 'Dynamic Forms',
      'description' => 'This module is used to create dynamic forms.',
      'purchase_link' => 'https://hitechhrx.com/product/custom-form-addon-laravel/',
    ],
    'GeofenceSystem' => [
      'name' => 'Geofence System',
      'description' => 'This module is used to manage geofence for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/geofence-attendance-addon-laravel/',
    ],
    'IpAddressAttendance' => [
      'name' => 'IP Address Attendance',
      'description' => 'This module is used to manage attendance based on IP Address.',
      'purchase_link' => 'https://hitechhrx.com/product/ip-based-attendance-addon-laravel/',
    ],
    'LoanManagement' => [
      'name' => 'Loan Management',
      'description' => 'This module is used to manage loans for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/loan-request-addon-laravel/',
    ],
    'NoticeBoard' => [
      'name' => 'Notice Board',
      'description' => 'This module is used to manage notice board for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/notice-board-addon-laravel/',
    ],
    'OfflineTracking' => [
      'name' => 'Offline Tracking',
      'description' => 'This module is used to track employees offline.',
      'purchase_link' => 'https://hitechhrx.com/product/offline-tracking-addon-laravel/',
    ],
    'PaymentCollection' => [
      'name' => 'Payment Collection',
      'description' => 'This module is used to collect payments from customers.',
      'purchase_link' => 'https://hitechhrx.com/product/payment-collection-addon-laravel/',
    ],
    'ProductOrder' => [
      'name' => 'Product Order',
      'description' => 'This module is used to manage product orders.',
      'purchase_link' => 'https://hitechhrx.com/product/product-order-system-addon-laravel/',
    ],
    'QRAttendance' => [
      'name' => 'QR Attendance',
      'description' => 'This module is used to manage attendance using QR Code.',
      'purchase_link' => 'https://hitechhrx.com/product/qr-attendance-addon-laravel/',
    ],
    'SiteAttendance' => [
      'name' => 'Site Attendance',
      'description' => 'This module is used to manage attendance based on site.',
      'purchase_link' => 'https://hitechhrx.com/product/site-attendance-addon-laravel/',
    ],
    'TaskSystem' => [
      'name' => 'Task System',
      'description' => 'This module is used to manage tasks for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/task-system-addon-laravel/',
    ],
    'UidLogin' => [
      'name' => 'One Tap Login',
      'description' => 'This module is used to login using UID.',
      'purchase_link' => 'https://hitechhrx.com/product/uid-login-addon-laravel/',
    ],
    'AiChat' => [
      'name' => 'AI Business Assistant',
      'description' => 'This module is used to chat with AI.',
      'purchase_link' => '#',
    ],
    'DigitalIdCard' => [
      'name' => 'Digital ID Card',
      'description' => 'This module is used to manage digital ID cards for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/digital-id-card-addon/',
    ],
    'DynamicQrAttendance' => [
      'name' => 'Dynamic QR Attendance',
      'description' => 'This module is used to manage attendance using dynamic QR Code.',
      'purchase_link' => 'https://hitechhrx.com/product/dynamic-qr-attendance-addon/',
    ],
    'Payroll' => [
      'name' => 'Payroll Management',
      'description' => 'This module is used to manage payroll for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/payroll-management-addon/',
    ],
    'SalesTarget' => [
      'name' => 'Sales Target',
      'description' => 'This module is used to manage sales targets for employees.',
      'purchase_link' => 'https://hitechhrx.com/product/sales-target-addon/',
    ],
    'FaceAttendance' => [
      'name' => 'Face Attendance',
      'description' => 'This module is used to manage attendance using face recognition.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
    'Approvals' => [
      'name' => 'Approvals',
      'description' => 'This module is used to manage approvals for employees from mobile app.',
      'purchase_link' => 'https://hitechhrx.com/net-saas-addons/',
    ],
  ];
  public const BuiltInRoles = ['admin', 'hr', 'employee', 'office_employee', 'manager'];
}
