<?php

namespace App\Constants;

class ModuleConstants
{
  public const BREAK = 'BreakSystem';
  public const DOCUMENT = 'DocumentManagement';
  public const DATA_IMPORT_EXPORT = 'DataImportExport';
  public const DYNAMIC_FORMS = 'DynamicForms';
  public const GEOFENCE = 'GeofenceSystem';
  public const IP_ADDRESS_ATTENDANCE = 'IpAddressAttendance';
  public const LOAN_MANAGEMENT = 'LoanManagement';
  public const MANAGER_APP = 'ManagerApp';
  public const NOTICE_BOARD = 'NoticeBoard';
  public const OFFLINE_TRACKING = 'OfflineTracking';
  public const PAYMENT_COLLECTION = 'PaymentCollection';
  public const PRODUCT_ORDER = 'ProductOrder';
  public const QR_ATTENDANCE = 'QrAttendance';
  public const SITE_ATTENDANCE = 'SiteAttendance';
  public const FACE_ATTENDANCE = 'FaceAttendance';
  public const AI_CHATBOT = 'AiChat';
  public const NOTES = 'Notes';
  public const ASSETS = 'Assets';
  public const DYNAMIC_QR_ATTENDANCE = 'DynamicQrAttendance';
  public const TASK_SYSTEM = 'TaskSystem';
  public const UID_LOGIN = 'UidLogin';
  public const DIGITAL_ID_CARD = 'DigitalIdCard';
  public const PAYROLL = 'Payroll';
  public const SALES_TARGET = 'SalesTarget';
  public const APPROVALS = 'Approvals';
  public const DISCIPLINARY_ACTIONS = 'DisciplinaryActions';
  public const HR_POLICIES = 'HRPolicies';
  public const LEAVE_MANAGEMENT = 'LeaveManagement';
  public const EXPENSE_MANAGEMENT = 'ExpenseManagement';
  public const CLIENT_VISIT = 'ClientVisit';
  public const CHAT_SYSTEM = 'ChatSystem';
  public const RECRUITMENT = 'Recruitment';
  public const CALENDAR = 'Calendar';
  public const SOS = 'SoS';

  public const STANDARD_MODULES = [
    self::LEAVE_MANAGEMENT,
    self::EXPENSE_MANAGEMENT,
    self::CLIENT_VISIT,
    self::CHAT_SYSTEM,
    self::SOS
  ];

  public const ATTENDANCE_TYPES = [
    self::IP_ADDRESS_ATTENDANCE,
    self::QR_ATTENDANCE,
    self::DYNAMIC_QR_ATTENDANCE,
    self::GEOFENCE,
    self::FACE_ATTENDANCE,
    self::SITE_ATTENDANCE
  ];

  public const ALL_MODULES_EXCEPT_ATTENDANCE = [
    self::BREAK,
    self::DOCUMENT,
    self::DATA_IMPORT_EXPORT,
    self::DYNAMIC_FORMS,
    self::LOAN_MANAGEMENT,
    self::MANAGER_APP,
    self::NOTICE_BOARD,
    self::OFFLINE_TRACKING,
    self::PAYMENT_COLLECTION,
    self::PRODUCT_ORDER,
    self::TASK_SYSTEM,
    self::UID_LOGIN,
    self::DIGITAL_ID_CARD,
    self::APPROVALS
  ];

  public const All_MODULES = [
    self::BREAK,
    self::DOCUMENT,
    self::DATA_IMPORT_EXPORT,
    self::DYNAMIC_FORMS,
    self::GEOFENCE,
    self::IP_ADDRESS_ATTENDANCE,
    self::LOAN_MANAGEMENT,
    self::MANAGER_APP,
    self::NOTICE_BOARD,
    self::OFFLINE_TRACKING,
    self::PAYMENT_COLLECTION,
    self::PRODUCT_ORDER,
    self::QR_ATTENDANCE,
    self::SITE_ATTENDANCE,
    self::TASK_SYSTEM,
    self::UID_LOGIN,
    self::DIGITAL_ID_CARD,
    self::LEAVE_MANAGEMENT,
    self::EXPENSE_MANAGEMENT,
    self::CLIENT_VISIT,
    self::CHAT_SYSTEM,
    self::FACE_ATTENDANCE,
    self::APPROVALS,
    self::DISCIPLINARY_ACTIONS,
    self::HR_POLICIES,
    self::PAYROLL
  ];
}
