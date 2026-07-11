# US-AD-10-2: Assign Certificate to Program

[← spec index](../README.md) · Area: Admin · **Status:** Spec

As an admin, I want to assign a certificate template to a program, so that students receive a certificate after completing all required courses within the program.

## Flow
1. 🔧 Open the program details
2. 🔧 Enable Program Certificate
3. 🔧 Select a certificate template
4. 🔧 Save the configuration
5. ⚙️ Link the certificate template to the program
6. ⚙️ Automatically issue the certificate when the student completes all required courses in the program

## Notes
- Assigning a certificate is optional.
- Students must complete all required courses before the certificate is issued.
- Optional courses do not prevent certificate issuance.
- Existing issued certificates are not affected by future configuration changes.

## Related
Programs are created in [US-AD-9-1](US-AD-9-1-create-program.md).
