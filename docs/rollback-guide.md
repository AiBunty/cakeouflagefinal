# Rollback Guide

Rollback policy is code-first and non-destructive.

## Safety Principles

- Never delete live data.
- Never run destructive SQL as rollback.
- Never remove runtime directories.
- Preserve production .env and all media/uploads.

## Trigger Conditions

- Health endpoint failure after deploy.
- Auth/session regression.
- Checkout or order flow regression.
- Admin critical page failure.

## Rollback Steps

1. Identify release entry in `storage/deployment/deployment-history.json`.
2. Capture failing release id, commit, and log file in `deploy/logs/`.
3. Identify previous known-good commit from Git history.
4. Redeploy previous known-good code using normal deploy script:
   - run deploy against that known-good snapshot.
5. Run `Post Deploy Validation`.
6. Record incident timeline and root cause notes.

## Database Rollback Policy

- Additive migrations are not rolled back destructively.
- If migration introduced a conflict, resolve forward with a safe additive patch.
- Escalate to DBA-approved plan for any non-additive correction.

## Hotfix Recovery

- If a hotfix fails, deploy the previous version of the same files only.
- Re-run validation and confirm lock/history consistency.

## Communication Template

- Issue detected at:
- Affected surfaces:
- Release id:
- Current commit:
- Rollback commit:
- Validation status after rollback:
- Follow-up action:
