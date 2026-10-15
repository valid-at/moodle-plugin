# Changes

## 1.0.0 (2026-10-15)

First stable release. Supports Moodle 4.1 to 5.3. Replaces the beta of 2026-10-02; there are no database changes, so
an installed beta updates without further steps. The code follows the current Moodle coding style, and the tests use
PHPUnit attributes in addition to annotations.

- Issues a certificate on valid.at for every course completion in courses where it is switched on.
- Settings per course:
  - kind of certificate,
  - validity,
  - text on the PDF,
  - whether the email address is sent,
  - whether valid.at emails the learner.
- Course page with:
  - list of certificates,
  - revoking with a reason,
  - trying again,
  - issuing for past completions.
- "My certificates on valid.at" in the user profile.
- Connection test in the site settings.
- Privacy API, English and German language strings, PHPUnit tests.
