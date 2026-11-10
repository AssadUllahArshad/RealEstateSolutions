<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['users.view', 'access.manage', 'inventory.view', 'inventory.export', 'inventory.manage', 'configuration.manage'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }

        foreach (['super_admin', 'admin', 'accountant', 'sales_agent'] as $role) {
            Role::findOrCreate($role, 'web');
        }

        foreach (['inventory.reservation', 'inventory.release', 'inventory.block'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['inventory.view', 'inventory.reservation', 'inventory.release', 'inventory.block']);
        }
        Role::findByName('sales_agent', 'web')->givePermissionTo(['inventory.view', 'inventory.reservation']);
        foreach (['customers.view', 'customers.manage', 'customers.view_all', 'customers.assign'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['customers.view', 'customers.manage', 'customers.view_all', 'customers.assign']);
        }
        Role::findByName('sales_agent', 'web')->givePermissionTo(['customers.view', 'customers.manage']);
        Permission::findOrCreate('sales.drafts', 'web');
        Permission::findOrCreate('finance.allocations.post', 'web');
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.allocations.post');
        }
        Permission::findOrCreate('finance.allocations.correct', 'web');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.allocations.correct');
        }
        Permission::findOrCreate('sales.confirm', 'web');
        Permission::findOrCreate('sales.changes.request', 'web');
        Permission::findOrCreate('sales.changes.approve', 'web');
        foreach (['super_admin', 'admin', 'sales_agent', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['sales.confirm', 'sales.changes.request']);
        }
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('sales.changes.approve');
        }
        Permission::findOrCreate('finance.receipts.post', 'web');
        Permission::findOrCreate('finance.receipts.reverse', 'web');
        Permission::findOrCreate('finance.expenses.view', 'web');
        Permission::findOrCreate('finance.expenses.post', 'web');
        Permission::findOrCreate('finance.charges.view', 'web');
        Permission::findOrCreate('finance.charges.manage', 'web');
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['finance.receipts.post', 'finance.expenses.view', 'finance.expenses.post']);
            Role::findByName($role, 'web')->givePermissionTo('finance.charges.view');
        }
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.receipts.reverse');
            Role::findByName($role, 'web')->givePermissionTo('finance.charges.manage');
        }
        foreach (['finance.accounts.view', 'finance.accounts.manage', 'finance.receipts.view', 'finance.receipts.view_all', 'finance.receipts.prepare'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['finance.accounts.view', 'finance.receipts.view_all']);
        }
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.accounts.manage');
        }
        foreach (['super_admin', 'admin', 'accountant', 'sales_agent'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(['finance.receipts.view', 'finance.receipts.prepare']);
        }
        Role::findByName('sales_agent', 'web')->givePermissionTo('finance.charges.view');
        Permission::findOrCreate('sales.discounts', 'web');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('sales.discounts');
        }
        Permission::findOrCreate('business-settings.manage', 'web');
        Role::findByName('super_admin', 'web')->givePermissionTo('business-settings.manage');
        Permission::findOrCreate('reports.owners.view', 'web');
        foreach (['finance.owner-payouts.view', 'finance.owner-payouts.prepare', 'finance.owner-payouts.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['finance.refunds.view', 'finance.refunds.prepare', 'finance.refunds.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.refunds.view');
        }
        Role::findByName('accountant', 'web')->givePermissionTo('finance.refunds.prepare');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.refunds.approve');
        }
        foreach (['finance.commissions.view', 'finance.commissions.prepare', 'finance.commissions.approve', 'finance.commissions.configure', 'finance.commissions.correct'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.commissions.view');
        }
        Role::findByName('accountant', 'web')->givePermissionTo('finance.commissions.prepare');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.commissions.approve');
        }
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.commissions.configure');
        }
        Role::findByName('super_admin', 'web')->givePermissionTo('finance.commissions.correct');
        foreach (['finance.commissions.view', 'finance.commissions.prepare', 'finance.commissions.approve'] as $ability) {
            Permission::findOrCreate($ability, 'web');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.commissions.view');
        }
        Role::findByName('accountant', 'web')->givePermissionTo('finance.commissions.prepare');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.commissions.approve');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.owner-payouts.view');
        }
        Role::findByName('accountant', 'web')->givePermissionTo('finance.owner-payouts.prepare');
        foreach (['super_admin', 'admin'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('finance.owner-payouts.approve');
        }
        foreach (['super_admin', 'admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('reports.owners.view');
        }
        foreach (['super_admin', 'admin', 'sales_agent', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo('sales.drafts');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::findByName('super_admin', 'web')->givePermissionTo(['users.view', 'access.manage', 'inventory.view', 'inventory.export', 'inventory.manage', 'configuration.manage']);
        // Office administrators and accountants can perform the complete sales-agent workflow.
        foreach (['admin', 'accountant'] as $role) {
            Role::findByName($role, 'web')->givePermissionTo(Role::findByName('sales_agent', 'web')->permissions);
        }
        Role::findByName('accountant', 'web')->givePermissionTo('customers.view_all');
        Role::findByName('admin', 'web')->givePermissionTo(['inventory.export', 'inventory.manage', 'configuration.manage']);
        // Receipt preparation grants do not authorize recognized money or ledger posting.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
