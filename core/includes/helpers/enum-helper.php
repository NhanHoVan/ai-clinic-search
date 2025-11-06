<?php

namespace AICLINIC\Core\Includes\Helpers;

enum Status_Type_Enum: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case UNPAID = 'unpaid';
}

enum Pricing_Plan_Enum: string
{
    case BASIC = 'basic';
    case GOLD = 'gold';
}

enum Action_Type_Enum: string
{
    case IMPORT = 'import';
    case EXPORT = 'export';
}

enum Import_Export_Status_Enum: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
}

enum Mode_Generator_Enum: string
{
    case IMMEDIATE = 'immediate';
    case QUEUE = 'queue';
}
