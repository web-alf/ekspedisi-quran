<?php

namespace App\Enums;

enum RoleEnum: string
{
    case SUPER_ADMIN = 'super-admin';
    case MANAGER = 'manager';
    case CUSTOMER_SERVICE = 'customer-service';
    case WAREHOUSE = 'warehouse';
    case SUPERVISOR = 'supervisor';
    case COURIER = 'courier';
}
