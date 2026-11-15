# Staff identity and mailbox plan

[Planning index](README.md) · [RBAC matrix](RBAC_MATRIX.md) · [Deployment checklist](RESTORE_DRILL_AND_DEPLOYMENT.md)

Status: the three staff identities and mailbox names below were supplied or authorized on 2026-11-15. Mailboxes must be created in the `datarealestate.online` hosting control panel before outbound verification or password-reset email is enabled. Passwords are never stored in this document.

| Person | Phone | Business mailbox | Application role |
|---|---|---|---|
| Amir Ali | Stored only in authorized environments | `amir.ali@datarealestate.online` | Main administrator |
| Raja Irshad | Stored only in authorized environments | `raja.irshad@datarealestate.online` | Main administrator |
| Tabish Hafeez | Stored only in authorized environments | `tabish.hafeez@datarealestate.online` | Accountant |

The application now stores an optional staff phone number separately from customer contact data. Authorized Team screens and the protected Team CSV include it. Staff can keep their own phone current from Profile settings.

## Safe provisioning

Run the trusted console command on the target environment. It prompts for a password only when creating a new account:

```text
php artisan estateflow:staff amir.ali@datarealestate.online --name="Amir Ali" --phone=CONFIRMED_PHONE --role=super_admin
php artisan estateflow:staff raja.irshad@datarealestate.online --name="Raja Irshad" --phone=CONFIRMED_PHONE --role=super_admin
php artisan estateflow:staff tabish.hafeez@datarealestate.online --name="Tabish Hafeez" --phone=CONFIRMED_PHONE --role=accountant
```

Both admin roles reflect the 2026-11-15 decision. To rename an existing account while preserving its password, add its current address:

```text
php artisan estateflow:staff amir.ali@datarealestate.online --match-email=OLD_ADDRESS --name="Amir Ali" --phone=CONFIRMED_PHONE --role=super_admin
```

The command is transactional and writes `access.staff_provisioned` or `access.staff_profile_updated` audit evidence. A changed email is marked unverified. The existing password is never replaced during an update.

## Mail setup still required

Create all three mailboxes in o3host.pk, then configure production SMTP credentials and send one verification and one password-reset test to each account. Until this is complete the application remains on its log mailer and cannot deliver real account email.

