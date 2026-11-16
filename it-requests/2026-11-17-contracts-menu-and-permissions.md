# IT request: Funding contracts in the menu, and the donor and contract permissions

| | |
|---|---|
| Requested by | CT department (with the finance team) |
| Department | ct |
| Date | 2026-11-17 |
| Website / page | Financial website: menu, Donors, Funding contracts |
| Branch | ct/contracts-menu-permissions |
| Status | Waiting for IT |

## 1. What is wanted (plain language)

#11 (donors and funding contracts) is merged and finance can add donors. But **the Funding contracts screen is not in the menu**, and **nobody but the super user can open it**, because its screen and permissions are not in `config/`. CT may not edit `config/`. This is the reminder of the "Needs IT attention" items of #11, as a short checklist.

### 1.1 `config/navigation.php`

Add the `contracts` screen to the Projects group, with the path the module route already uses (`routes/modules/donors-and-contracts.php`):

```php
'projects' => ['projwin', 'budgetlines', 'blcats', 'lifetime', 'programs', 'projects', 'contracts'],

// paths
'contracts' => 'projects/contracts',
```

The labels already exist: `navigation.screens.contracts` and `navigation.descriptions.contracts` in both languages.

### 1.2 `config/permissions.php`

As agreed with finance on 2026-11-16 (#9, sections 1.1 and 1.15). `tests/Feature/DonorsAndContractsTest.php` sets the same values.

| Role | `donors` | `contracts` |
|---|---|---|
| Head Office | all (already) | all (comes with `$everyScreen` once 1.1 is done) |
| Financial Manager | **view, create, edit** (today: view only) | **view, create, edit, approve** |
| Accountant | view (already) | **view, create, edit** |
| General Manager | **view** | **view, approve** (second approval of a closing) |
| Program Manager | **view** | **view** |
| Project Manager | **view** | **view** (only the contracts of their own projects; the screen filters them) |
| Cashier | — | — |

### 1.3 How finance checks it

1. **Financial Manager:**
   - Sees "Funding contracts" under Projects in the menu.
   - Can add a donor.
   - Can create a contract and approve it.
2. **Accountant:** can create a contract but has no "Approve contract" button.
3. **General Manager:** can open contracts and give the second approval of a closing.

## 2. What was found (read-only)

- `origin/main` after #10 and #11: `config/navigation.php` has no `contracts` screen, and `config/permissions.php` has no `contracts` entry. The Financial Manager has only `view` on `donors`.
- The screens, routes, tables and tests are in place (#11). Nothing else needs to change for this request.
- Until this is done, the contracts screen can only be reached through the "Funding contracts" button on the Donors page, or the address `/projects/contracts`. Both are refused (403) for every role except the super user.

## 3. Questions for IT (answer BEFORE any work starts)

1. Is this change possible and safe to do? **(Yes / No / Only with changes)**
2. Which backend files must change? `config/navigation.php` and `config/permissions.php` only.
3. Does it affect the database or existing data? No.
4. Does it affect other pages or other websites that share this code? Only the menu (one new item) and who sees Donors and Funding contracts.
5. Does the view need new data from the backend? No.
6. Is there a front-end-only alternative? No: screens and permissions live in `config/`.
7. Any security or permission impact? Yes, it grants the permissions in the table above, as agreed with finance.
8. Who does the work: IT directly (CT cannot edit `config/`).
9. Needs staging test, deploy window, or rollback plan? Rollback = revert the two config changes. Clear the config cache after deploying if it is cached.
10. Priority / deadline: **high**. Finance cannot start entering contracts without it.

## IT answers

| | |
|---|---|
| Decision | Approved / Approved with changes / Rejected |
| Answered by | |
| Date | |
| Implemented by | |
| Pull request | |

<Notes and answers to the questions above:>
