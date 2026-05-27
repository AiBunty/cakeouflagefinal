# Deployment Checklist (Release Gate)

## 1) Infrastructure / Runtime

- [x] Local containers boot and app reachable (`http://localhost:8080`)
- [x] Database reachable and writable
- [x] Session persistence directory writable (`storage/sessions`)
- [x] API response logging enabled for audit traces

## 2) Functional Regression (Core)

- [x] Online flow can place and complete order lifecycle
- [x] Manual-mode lifecycle can complete (creation via fallback seed)
- [x] BYOC quote accept flow can complete lifecycle
- [ ] Checkout preview endpoint stable (currently `422`)
- [ ] Refund request endpoint functional (currently `500`)
- [ ] Refund process endpoint functional (currently `500`)

## 3) Accounting / Finance

- [x] Financial transactions posted for tested completed orders
- [x] GL entries posted for tested completed orders
- [ ] Invoice generation present for tested completed orders
- [ ] Refund transaction records verified via live refund path

## 4) CRM / Communication / Notification

- [ ] Communication logs created for tested order lifecycle events
- [ ] CRM push logs created for tested lifecycle events

## 5) Fulfilment Capacity Controls

- [ ] Slot reservation rows created/updated for tested fulfilment flows

## 6) Admin Observability

- [x] Dashboard summary endpoint healthy
- [x] Finance summary endpoint healthy
- [x] Reports summary endpoint healthy
- [x] Orders listing endpoint healthy
- [ ] Refund listing endpoint authorized for operational role (`403` in current run)

## 7) Release Gate Decision

- **BLOCK RELEASE** until unchecked items above are resolved and retested.
