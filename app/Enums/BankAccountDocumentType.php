<?php

namespace App\Enums;

enum BankAccountDocumentType: string
{
    // Personne physique
    case CertifiedIdCopy = 'CERTIFIED_ID_COPY';
    case DomicileProof = 'DOMICILE_PROOF';
    case IncomeProof = 'INCOME_PROOF';
    case ClientSignature = 'CLIENT_SIGNATURE';

    // Personne morale
    case NifCopy = 'NIF_COPY';
    case RccmCopy = 'RCCM_COPY';
    case ApprovalOrReceiptCopy = 'APPROVAL_OR_RECEIPT_COPY';
    case StatutesCopy = 'STATUTES_COPY';
    case MandateCopy = 'MANDATE_COPY';
    case DirectorsIdCopies = 'DIRECTORS_ID_COPIES';
    case UboIdCopies = 'UBO_ID_COPIES';
    case Other = 'OTHER';

    /**
     * @return list<self>
     */
    public static function forPhysicalPerson(): array
    {
        return [
            self::CertifiedIdCopy,
            self::DomicileProof,
            self::IncomeProof,
            self::ClientSignature,
            self::Other,
        ];
    }

    /**
     * @return list<self>
     */
    public static function forLegalEntity(): array
    {
        return [
            self::NifCopy,
            self::RccmCopy,
            self::ApprovalOrReceiptCopy,
            self::StatutesCopy,
            self::MandateCopy,
            self::DirectorsIdCopies,
            self::UboIdCopies,
            self::ClientSignature,
            self::Other,
        ];
    }
}
