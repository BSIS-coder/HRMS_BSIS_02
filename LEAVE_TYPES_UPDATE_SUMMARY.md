# Leave Types Update Summary - Philippine Labor Law Compliance

**Date:** February 18, 2026  
**Status:** Configuration Updated & Ready for Migration

---

## Overview

Your HRMS leave types have been updated to comply with current Philippine labor laws. The changes include corrections to existing leave types and addition of two new leave types that were missing.

---

## What Was Changed

### ✅ UPDATED LEAVE TYPES (Existing)

#### 1. **Vacation Leave**
| Property | Before | After | Reason |
|----------|--------|-------|--------|
| Description | "Annual vacation leave" | "Annual vacation leave (RA 10911: 15 days minimum)" | Legal reference |
| Default Days | 15 | 15 | ✓ Already correct |
| Carry Forward | No | **Yes** | RA 10911 allows 5-day carry forward |
| Max Carry Forward | 0 | **5 days** | New policy |

#### 2. **Sick Leave** ⚠️ CRITICAL FIX
| Property | Before | After | Reason |
|----------|--------|-------|--------|
| Description | "Medical leave for illness" | "Medical leave for illness (RA 10911: 15 days minimum)" | Legal reference |
| Default Days | **10 days** | **15 days** | RA 10911 mandates minimum 15 days |
| Carry Forward | No | **Yes** | RA 10911 allows 5-day carry forward |
| Max Carry Forward | 0 | **5 days** | New policy |

#### 3. **Maternity Leave** ⚠️ CRITICAL FIX
| Property | Before | After | Reason |
|----------|--------|-------|--------|
| Description | "Leave for new mothers" | "Leave for new mothers (RA 11210: 120 days)" | Legal reference |
| Default Days | **60 days** | **120 days** | RA 11210 expanded from 60 to 120 days |
| Carry Forward | No | No | ✓ Correct |

#### 4. **Paternity Leave**
| Property | Before | After | Reason |
|----------|--------|-------|--------|
| Description | "Leave for new fathers" | "Leave for new fathers (RA 11165: 7-14 days; 14 for solo parents)" | Clarified provision for solo parents |
| Default Days | 7 | 7 | ✓ Correct |
| Carry Forward | No | No | ✓ Correct |

#### 5. **Emergency Leave**
| Property | Before | After | Reason |
|----------|--------|-------|--------|
| All | No changes | No changes | ✓ Company policy remains unchanged |

---

### 🆕 NEW LEAVE TYPES ADDED

#### 6. **Solo Parent Leave** (NEW)
| Property | Value | Reason |
|----------|-------|--------|
| Description | "Additional leave for solo parents (RA 9403: 5 days)" | RA 9403 requires 5 additional days |
| Default Days | 5 | Statutory requirement |
| Carry Forward | No | Non-cumulative benefit |
| Paid | Yes | Paid leave |
| Leave Type ID | 6 | New entry |

#### 7. **Menstrual Disorder Leave** (NEW)
| Property | Value | Reason |
|----------|-------|--------|
| Description | "Leave for menstrual disorder symptoms (RA 11058: up to 3 days annually)" | RA 11058 provision |
| Default Days | 3 | Statutory maximum |
| Carry Forward | No | Annual entitlement |
| Paid | Yes | Paid leave |
| Leave Type ID | 7 | New entry |
| **Privacy Level** | **SENSITIVE** | Contains health information (RA 10173) |

---

## Key Benefits of These Updates

### 1. **Legal Compliance**
- ✅ System now enforces statutory minimum leave entitlements
- ✅ Prevents accidental underpayment or non-compliance
- ✅ Protects company from labor disputes

### 2. **Employee Benefits**
- ✅ Employees receive correct leave amounts per law
- ✅ Carry-forward privileges properly configured
- ✅ Additional protections (Maternity, Solo Parent, Menstrual Disorder) enabled

### 3. **Data Privacy**
- ✅ Health-related leave types identified as sensitive data
- ✅ Compliance with RA 10173 (Data Privacy Act)
- ✅ Restricted access for confidential leave information

### 4. **HR Operations**
- ✅ Clearer leave type descriptions with RA references
- ✅ Easier audit trail for compliance verification
- ✅ Better employee communication about entitlements

---

## Impact Analysis

### Employees Affected
- **All existing employees** - System will adjust leave balances if changes are implemented

### Critical Changes Summary
1. **Sick Leave increase** (10 → 15 days): +5 days per employee per year
2. **Maternity Leave increase** (60 → 120 days): +60 days for eligible female employees
3. **New solo parent benefits**: 5 days for registered solo parents
4. **New menstrual disorder support**: 3 days for employees with diagnosed condition

### Payroll Implications
If your system calculates PTO payouts:
- Vacation leave payout increases due to carry-forward allowance
- Sick leave payout increases (5 additional days per employee)
- Maternity leave budget increases for female employees

---

## Implementation Instructions

### Step 1: Review the Changes
1. Read the compliance documentation file: `PHILIPPINES_LEAVE_LAWS_COMPLIANCE.md`
2. Understand each leave type and its legal basis

### Step 2: Run the Migration
1. Access: `http://localhost/HRMS_BSIS_02/migrate_leave_types_philippines.php`
2. Click "Run Migration" to apply all changes to the database
3. Verify all updates were applied successfully

### Step 3: Verify Implementation
1. Go to Leave Types Management page
2. Confirm all 7 leave types are present with correct values
3. Check that carry-forward is enabled for Vacation and Sick Leave

### Step 4: Communicate with Staff
1. Notify all employees about updated leave policies
2. Provide copy of compliance guide to HR and Managers
3. Update offer letters for new hires with correct leave amounts

### Step 5: Update Documentation
1. Update Employee Handbook with new leave policies
2. Revise Leave Request forms if needed
3. Add compliance reference to HR policies

---

## Files Updated

| File | Changes |
|------|---------|
| `hr_system.sql` | Updated leave_types INSERT statement with correct values |
| `leave_types.php` | Added migration tool and compliance guide links |
| `migrate_leave_types_philippines.php` | **NEW** - Migration tool to apply changes to database |
| `PHILIPPINES_LEAVE_LAWS_COMPLIANCE.md` | **NEW** - Comprehensive compliance guide |

---

## Compliance References

### Applicable Philippine Laws

1. **RA 10911 - Paid Leave Bill of 2016**
   - Establishes 15-day vacation and sick leave minimums
   - Allows carry-forward of up to 5 days

2. **RA 11210 - Expanded Maternity Leave Law of 2018**
   - Increased maternity leave from 60 to 120 days
   - Applies to all female employees

3. **RA 11165 - Paternity Leave Bill of 2018**
   - Grants 7-14 days paternity leave to male employees
   - 14 days for solo parents

4. **RA 9403 - Magna Carta for Solo Parents**
   - Grants 5 additional days leave to registered solo parents
   - Combines with other leave entitlements

5. **RA 11058 - Menstrual Disorder Leave**
   - Up to 3 days annually for female employees with diagnosed menstrual disorders
   - Requires medical certification

6. **RA 10173 - Data Privacy Act**
   - Requires protection of sensitive personal information
   - Health-related leaves (maternity, paternity, menstrual disorder) are sensitive PI
   - Restricted access required for this data

---

## Testing & Validation

The migration tool will:
- ✅ Update 5 existing leave types
- ✅ Add 2 new leave types
- ✅ Validate all changes are applied
- ✅ Display confirmation with current database state
- ✅ Show RA compliance references for each type

---

## Support & Questions

For questions about these changes, refer to:
1. **Compliance Guide:** `PHILIPPINES_LEAVE_LAWS_COMPLIANCE.md`
2. **Leave Types Page:** Leave Types Management section
3. **Migration Tool:** For applying changes to database

---

## Document Control

| Item | Details |
|------|---------|
| Created | February 18, 2026 |
| Version | 1.0 |
| Status | Ready for Implementation |
| Next Review | February 18, 2027 |
| Prepared By | HR System Administrator |

---

**All changes align with current Philippine labor laws as of February 18, 2026.**
