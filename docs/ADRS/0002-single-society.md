# ADR 0002 — One configurable society per installation

Date: 2026-10-31. Status: accepted scope; technical implementation described below.

[Index](../README.md) · [Inventory evidence](../INVENTORY_STATUS.md) · [Decision register](../FINANCIAL_DECISIONS.md)

## Decision and authority

The user clarified that inventory is for one society/office while remaining dynamic enough for any society to use. Interpret D01 as **one society and office per installation**, with user-configured society/office details, blocks, plot sizes and plot identifiers. Each separate society can use its own installation/database. This does not authorize shared-staff multi-society access, separate branch scopes or SaaS tenancy.

## Implementation

- A permanent `societies` profile slot has ID 1. A MySQL CHECK plus primary key prevents a second society. Its name starts null until staff configure it; no actual society identity is invented by a seeder.
- Blocks reference that profile and have unique normalized codes. Plot sizes are installation-local, configurable labels with positive decimal marla areas. No fixed block letters or size list is seeded.
- Plots reference a block and size. Plot number uniqueness is `(block_id, number)`. Number/code input is trimmed, whitespace-normalized and uppercased; the database collation additionally protects case-insensitive uniqueness.
- Optional catalogue prices use DECIMAL(18,2), decimal string requests/responses and string-based PKR formatting. These prices are not approved contract values, discounts, installments or entitlements.
- Catalogue mutations lock the permanent profile row, reauthorize, save and append inventory audit snapshots atomically. This simple installation-wide catalogue lock favors correctness at this stage; review contention before large imports. Database uniqueness remains the final safeguard.
- Initial abilities `inventory.view`, `inventory.manage`, `configuration.manage` are explicit and granted to main admin only. Other role grants still require delegation review; role names confer no extra access automatically.

## Boundary and consequences

D03 is unresolved. Catalogue rows have **no operational availability/status field** in this slice. No plot is automatically offered for sale, reserved, allotted or marked sold. Listing and grid show recorded catalogue data; reservations, expiry, approval and occupancy invariants come in a separately approved lifecycle slice. Joint ownership/transfers and all financial policies remain unchanged.

One office's display/contact details do not create branch membership or isolation semantics. Multi-society or branch requirements later need a new decision, migration and access-control design; they cannot be enabled by inserting another society row. Existing accepted contracts must eventually snapshot catalogue descriptions/prices before sale modules are introduced.
