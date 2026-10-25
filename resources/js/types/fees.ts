export type DuesScreen = App.Domain.Fees.Data.DuesScreenData;
export type DueRow = App.Domain.Fees.Data.DueRowData;
export type DuesSummary = App.Domain.Fees.Data.DuesSummaryData;
export type FeeOption = App.Domain.Fees.Data.FeeOptionData;
export type FeeHead = App.Domain.Fees.Data.FeeHeadData;
export type InvoiceStatus = App.Domain.Fees.Enums.InvoiceStatus;
export type PaymentStatus = App.Domain.Fees.Enums.PaymentStatus;
export type PaymentMode = App.Domain.Fees.Enums.PaymentMode;
export type GatewayReport = App.Domain.Fees.Enums.GatewayReport;
export type PaymentsScreen = App.Domain.Fees.Data.PaymentsScreenData;
export type PaymentRow = App.Domain.Fees.Data.PaymentRowData;
export type PaymentDetail = App.Domain.Fees.Data.PaymentDetailData;
export type PaymentLine = App.Domain.Fees.Data.PaymentLineData;
export type GatewayInfo = App.Domain.Fees.Data.GatewayInfoData;
export type Receipt = App.Domain.Fees.Data.ReceiptData;
export type FeeStructure = App.Domain.Fees.Data.FeeStructureData;
export type FeeGroup = App.Domain.Fees.Data.FeeGroupData;
export type FeeClassRow = App.Domain.Fees.Data.FeeClassRowData;
export type FeeInstallment = App.Domain.Fees.Data.InstallmentData;
export type FeeConcession = App.Domain.Fees.Data.ConcessionData;
export type CollectScreen = App.Domain.Fees.Data.CollectScreenData;
export type CollectStudent = App.Domain.Fees.Data.CollectStudentData;
export type CollectInstallment = App.Domain.Fees.Data.CollectInstallmentData;
export type CollectSearchResult = App.Domain.Fees.Data.CollectSearchResultData;
export type ParentFees = App.Domain.Fees.Data.ParentFeesData;
export type ParentInstallment = App.Domain.Fees.Data.ParentInstallmentData;
export type ParentPayment = App.Domain.Fees.Data.ParentPaymentData;

export type DuesFilters = {
    q: string;
    class: string | null;
    installment: string | null;
    status: 'open' | 'overdue' | 'due' | 'partial' | 'upcoming';
    sort: 'due' | 'balance' | 'class';
};

export type PaymentFilters = {
    q: string;
    status: 'all' | 'pending' | 'failed' | 'success';
    mode: string;
    from: string | null;
    to: string | null;
};
