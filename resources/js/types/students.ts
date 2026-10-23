/**
 * Short names for the generated student types (resources/js/types/generated.d.ts).
 * The shapes themselves come from the PHP Data objects; never edit them by hand.
 */
export type StudentList = App.Domain.Students.Data.StudentListData;
export type StudentRow = App.Domain.Students.Data.StudentRowData;
export type StudentProfile = App.Domain.Students.Data.StudentProfileData;
export type StudentGuardian = App.Domain.Students.Data.GuardianData;
export type StudentSibling = App.Domain.Students.Data.SiblingData;
export type StudentDocument = App.Domain.Students.Data.StudentDocumentData;
export type StudentOptions = App.Domain.Students.Data.StudentOptionsData;
export type StudentOption = App.Domain.Students.Data.OptionData;
export type SectionOption = App.Domain.Students.Data.SectionOptionData;
export type ImportPreview = App.Domain.Students.Data.ImportPreviewData;
export type ImportRow = App.Domain.Students.Data.ImportRowData;
export type ImportResult = App.Domain.Students.Data.ImportResultData;
export type TransferCertificate =
    App.Domain.Students.Data.TransferCertificateData;
export type House = App.Domain.School.Data.HouseData;
export type NumberingSettings = App.Domain.School.Data.SchoolSettingsData;
export type CampusSwitcher = App.Domain.School.Data.CampusSwitcherData;
export type SessionSwitcher = App.Domain.School.Data.SessionSwitcherData;

export type StudentFilters = {
    q: string;
    class: string | null;
    status: 'current' | 'left' | 'all';
};
