<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPER_ADMIN = 'super-admin';
    case MANAGER = 'manager';
    case CUSTOMER_SERVICE = 'customer-service';
    case WAREHOUSE = 'warehouse';
    case MANAGER_REPACKING = 'manager-repacking';
    case SPV_GUDANG = 'spv-gudang';
    case SUPERVISOR = 'supervisor';
    case COURIER = 'courier';
    case DISTRIBUSI = 'distribusi';
}
