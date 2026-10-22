# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users
Four internal roles, in a Syrian humanitarian organisation with offices in several cities. Bilingual (English and Arabic, RTL).
- **Accountant**: posts vouchers all day; many lines, accuracy and speed matter most.
- **Financial Manager**: reviews balances and reports, approves requests (USD 500+ rules apply).
- **Project managers**: look up their project balances, submit monthly requests.
- **General Manager (CEO)**: approves above USD 2,000, glances at summaries.

## Product Purpose
A web front end for the organisation's financial ledger (`hcsyria_financial_test`, owned by the legacy Windows program). Staff see where project money is, in which currency and cash box, and post and approve entries. Success: the right number is found in seconds and no one misreads a currency or a sign.

## Capabilities and Constraints
Laravel, Livewire, Flux, Tailwind 4. Two currencies (USD and SYP, with old and new lira). Legacy tables are never written. See `docs/PLAN-hcsyria_financial_test.md`.

## Product Principles
1. Numbers first: amounts are aligned, tabular, signed, and always show their currency.
2. Plain and static: no decorative motion; state changes are instant or a short fade.
3. One pattern per job: every table, form and amount looks and behaves the same on every screen.
4. Both languages are equal: nothing breaks in RTL; digits and minus signs stay left-to-right.
5. Safe by default: warnings say what is wrong and what to do; irreversible actions are explicit.

## Accessibility & Inclusion
Body text at least 14px, contrast 4.5:1, rows at least 44px tall, keyboard reachable, never colour alone (a loss is red and has a minus sign).
