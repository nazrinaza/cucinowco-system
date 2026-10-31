# Agent registration introducers

An existing active referral source can invite agents using:
`https://agents.cucinow.co/?ref=azim`
or directly: `https://agents.cucinow.co/register?ref=azim`.
The dashboard invitation link uses the direct registration URL.

HQ creates partner codes in Admin → Referrals (channel Partner). Approved, email-verified agents can use their existing code; their dashboard displays a separate agent invitation URL. Customer referral links remain unchanged.

The last valid invitation is retained for 90 days in a separate session/encrypted HttpOnly cookie. Invalid codes do not replace an existing valid invitation. The source is checked again on registration; inactive/suspended sources are not attributed. No valid attribution means Direct. Existing registrations remain Direct; historical introducers cannot be inferred.

HQ sees the introducer name, code and channel under Admin → Agents and the agent review page. Snapshot fields retain attribution if the original source is renamed or deleted. This is link attribution, not verified personal identity or a commission entitlement. Registration requests cannot set these fields directly. Customer visit/lead counters are unaffected.

## Deployment
Deploy code and run the normal migration command with the server's verified PHP binary:
`php artisan migrate --force`
New migration: `2026_10_31_000001_add_agent_introducer.php`.
No production migration is performed by this change. Use the existing cPanel deployment workflow; do not guess the PHP path. No frontend rebuild is needed for these Blade-only changes.

## Verification
Open an invitation in a private browser, follow login → registration, register a test account, and check its introducer under Admin → Agents. Also check a fresh private browser without a referral displays Direct. Test registrations send normal verification emails.

Automated tests use fake mail and an isolated SQLite database:
`php artisan test --filter='AgentIntroducerTest|AgentPortalTest|ReferralTrackingTest'`
