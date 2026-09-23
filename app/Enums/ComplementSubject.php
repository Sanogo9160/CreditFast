<?php

namespace App\Enums;

enum ComplementSubject: string
{
    case Piece = 'PIECE';
    case Information = 'INFORMATION';
    case FieldVisit = 'FIELD_VISIT';
    case Guarantee = 'GUARANTEE';
}
