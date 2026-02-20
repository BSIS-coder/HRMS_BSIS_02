# Philippine Leave Laws Compliance Guide

## Overview
This HRMS has been configured to comply with Philippine labor laws regarding leave entitlements. All leave types and their default days are based on the following Republic Acts (RAs).

---

## Leave Types & Philippine Labor Laws

### 1. **VACATION LEAVE** (RA 10911 - Paid Leave Bill of 2016)
- **Minimum Days Entitlement:** 15 days per year
- **Application Period:** Based on calendar year
- **Carry Forward Policy:** Yes, up to 5 days can be carried forward to the next year
- **Paid/Unpaid:** Paid leave
- **Key Provisions:**
  - New employees get pro-rata benefits based on service record
  - Can be accumulated and converted to cash (with restrictions per company policy)
  - At least 10 days must be taken annually

**Implementation:** `default_days = 15, carry_forward = 1, max_carry_forward_days = 5`

---

### 2. **SICK LEAVE** (RA 10911 - Paid Leave Bill of 2016)
- **Minimum Days Entitlement:** 15 days per year
- **Application Period:** Based on calendar year
- **Carry Forward Policy:** Yes, up to 5 days can be carried forward
- **Paid/Unpaid:** Paid leave (with medical certificate requirement)
- **Usage:** Medical/health-related absences
- **Key Provisions:**
  - Requires medical certificate if absence exceeds 2 consecutive days
  - Can be used for personal health or immediate family member health issues

**Implementation:** `default_days = 15, carry_forward = 1, max_carry_forward_days = 5`

---

### 3. **MATERNITY LEAVE** (RA 11210 - Expanded Maternity Leave Law of 2018)
- **Days Entitlement:** 120 days (expanded from 60 days)
- **Application Period:** Before and after childbirth
- **Carry Forward Policy:** No carry forward
- **Paid/Unpaid:** Paid leave (100% of salary)
- **Eligibility:** All female employees
- **Key Provisions:**
  - Can be split: majority after delivery, some before if needed
  - 60 days can be within 5 years from date of birth
  - Notification required 2 weeks before due date
  - Extension possible for medical complications (separate entitlements)
  - Applies even if not covered by SSS

**Implementation:** `default_days = 120, carry_forward = 0, paid = 1`

---

### 4. **PATERNITY LEAVE** (RA 11165 - Paternity Leave Bill of 2018)
- **Days Entitlement:** 
  - **Regular Employees:** 7 days
  - **Solo Parents:** 14 days (per RA 11165 Section 2)
- **Application Period:** After childbirth or upon adoption
- **Carry Forward Policy:** No carry forward
- **Paid/Unpaid:** Paid leave (100% of salary)
- **Eligibility:** All male employees, including solo parents
- **Key Provisions:**
  - Must claim within 60 days of birth/adoption
  - Applies to both legitimate and illegitimate children
  - Adoptive fathers are covered
  - Requires birth certificate or legal documents

**Implementation:** `default_days = 7, carry_forward = 0, paid = 1`
**Note:** Solo parents receive 14 days through Solo Parent Leave entitlement (RA 9403)

---

### 5. **SOLO PARENT LEAVE** (RA 9403 - Magna Carta for Solo Parents)
- **Days Entitlement:** 5 days per year (additional to other leaves)
- **Application Period:** Can be used anytime during the year
- **Carry Forward Policy:** No carry forward
- **Paid/Unpaid:** Paid leave
- **Eligibility:** All solo parents enrolled in the Solo Parent Registry
- **Key Provisions:**
  - Available to both male and female solo parents
  - Combines with other leave benefits (Maternity/Paternity)
  - Requires solo parent certificate from DSWD or local government
  - Used for childcare, medical needs of child, etc.

**Implementation:** `default_days = 5, carry_forward = 0, paid = 1`

---

### 6. **MENSTRUAL DISORDER LEAVE** (RA 11058 - Special Provisions)
- **Days Entitlement:** Up to 3 days per year
- **Application Period:** Can be used when suffering from severe menstrual symptoms
- **Carry Forward Policy:** No standard carry forward (must be used annually)
- **Paid/Unpaid:** Paid leave
- **Eligibility:** Female employees with diagnosed menstrual disorders
- **Key Provisions:**
  - Optional - employee choice to use or not
  - Requires medical certification of menstrual disorder
  - Not required for doctors certification for each use
  - Confidential - employer must maintain data privacy per RA 10173
  - Does not reduce other leave entitlements

**Implementation:** `default_days = 3, carry_forward = 0, paid = 1`
**Privacy Note:** Per RA 10173 (Data Privacy Act), information about this leave type is sensitive personal information (health data) and access must be restricted.

---

### 7. **EMERGENCY LEAVE**
- **Days Entitlement:** 5 days per year (company policy)
- **Application Period:** For unexpected emergencies
- **Carry Forward Policy:** No carry forward
- **Paid/Unpaid:** UNPAID leave
- **Usage:** For domestic or personal emergencies
- **Key Provisions:**
  - Requires immediate notification to supervisor
  - Employer may request proof/explanation
  - Not governed by law but recommended as best practice

**Implementation:** `default_days = 5, carry_forward = 0, paid = 0`

---

## Summary Table

| Leave Type | Minimum Days | Carry Forward | RA Law | Paid? |
|---|---|---|---|---|
| Vacation Leave | 15 | Yes (5 max) | RA 10911 | ✅ Yes |
| Sick Leave | 15 | Yes (5 max) | RA 10911 | ✅ Yes |
| Maternity Leave | 120 | No | RA 11210 | ✅ Yes |
| Paternity Leave | 7 | No | RA 11165 | ✅ Yes |
| Solo Parent Leave | 5 | No | RA 9403 | ✅ Yes |
| Menstrual Disorder Leave | 3 | No | RA 11058 | ✅ Yes |
| Emergency Leave | 5 | No | Company Policy | ❌ No |

---

## Key Compliance Points

### ✅ Total Statutory Leave Entitlement (Minimum)
- **Vacation:** 15 days
- **Sick:** 15 days
- **Maternity:** 120 days (females only)
- **Paternity:** 7 days (males only)
- **Solo Parent:** 5 days (solo parents only)
- **Menstrual Disorder:** 3 days (eligible females only)

### ⚠️ Important Considerations

1. **Pro-rata Benefits:** New employees receive leave benefits pro-rated based on service period
2. **Medical Certificates:** Required for:
   - Sick leave beyond 2 consecutive days
   - Menstrual disorder leave (diagnosis required)
3. **Documentation Required:**
   - Birth certificate (Paternity/Maternity)
   - DSWD Solo Parent Certificate (Solo Parent Leave)
   - Medical certificate from licensed physician (Menstrual Disorder)
4. **Data Privacy (RA 10173):** 
   - Health-related leaves (Sick, Menstrual Disorder, Maternity) are sensitive personal information
   - Access must be restricted to authorized HR personnel only
   - Medical information must be kept confidential

---

## How to Use This System

### Adding Leave Requests
1. Employees submit leave requests through the system
2. System validates against available leave balance
3. Managers approve/reject based on company policy
4. Leave types and days are deducted automatically

### Managing Leave Types
- Access: **Leave Types Management** page (HR personnel only)
- Can edit default days if company policy allows higher amounts
- Cannot reduce below statutory minimums
- New leave types can be added per company policy

### Viewing Leave Balances
- Employees can view their leave balance in their portal
- Shows utilization per leave type
- Displays carry-forward amounts for applicable leaves

---

## Migration & Implementation

**Migration Date:** [Date when this configuration was applied]
**Applied By:** [HR Administrator Name]
**Next Review Date:** [Annual review date]

### Changes Made
This system was updated to enforce the following Philippine labor law requirements:

- ✅ Vacation Leave: Updated to 15 days minimum with carry forward
- ✅ Sick Leave: Updated from 10 to 15 days minimum with carry forward
- ✅ Maternity Leave: Updated from 60 to 120 days per RA 11210
- ✅ Paternity Leave: Confirmed at 7 days with note for 14-day solo parent variant
- ✅ Solo Parent Leave: Added per RA 9403
- ✅ Menstrual Disorder Leave: Added per RA 11058 with privacy safeguards
- ✅ Emergency Leave: Retained as unpaid company benefit

---

## Amendments & Updates

Document may be updated as new labor laws are enacted or policies change.

**Last Updated:** February 18, 2026
**Valid From:** February 18, 2026
**Next Review:** February 18, 2027
