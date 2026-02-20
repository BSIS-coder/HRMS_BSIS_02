# Holidays for Municipality of Norzagaray, Bulacan - 2025

## Summary

Based on official declarations and local government information, here are the holidays observed in Norzagaray, Bulacan for 2025:

## Regular Holidays (10 holidays)
These are nationwide regular holidays that apply to all municipalities including Norzagaray:

1. **New Year's Day** - January 1 (Wednesday)
2. **Araw ng Kagitingan** (Day of Valor) - April 9 (Wednesday)
3. **Maundy Thursday** - April 17
4. **Good Friday** - April 18
5. **Labor Day** - May 1 (Thursday)
6. **Independence Day** - June 12 (Thursday)
7. **National Heroes Day** - August 25 (Last Monday of August)
8. **Bonifacio Day** - November 30 (Sunday)
9. **Christmas Day** - December 25 (Thursday)
10. **Rizal Day** - December 30 (Tuesday)

**Pay Rules**: 100% of daily wage if not working, 200% if working

---

## Special Non-Working Holidays (8 holidays)
These are nationwide special non-working holidays:

1. **Chinese New Year** - January 29 (Wednesday)
2. **Black Saturday** - April 19
3. **Ninoy Aquino Day** - August 21 (Thursday)
4. **All Saints' Day Eve** - October 31 (Friday)
5. **All Saints' Day** - November 1 (Saturday)
6. **Feast of the Immaculate Conception of Mary** - December 8 (Monday)
7. **Christmas Eve** - December 24 (Wednesday)
8. **New Year's Eve** - December 31 (Wednesday)

**Pay Rules**: No work, no pay (unless company policy says otherwise), 130% if working

---

## Special Working Holiday (1 holiday)
Work proceeds as usual:

1. **EDSA People Power Revolution Anniversary** - February 25 (Tuesday)

**Pay Rules**: Regular daily wage (100%)

---

## Local Special Holidays (2 holidays)
These are specific to Norzagaray, Bulacan and the province:

1. **Araw ng Norzagaray** (Norzagaray Foundation Day) - August 13 (Wednesday)
   - **Note**: Not officially declared as a special non-working holiday by law
   - However, government work in municipal offices and classes in public schools are suspended
   - Celebrates the 165th founding anniversary in 2025
   - Includes the Casay Festival
   - House Bill No. 4395 was introduced to make this a special non-working holiday, but not yet enacted

2. **Bulacan Province Founding Anniversary** - August 15 (Friday)
   - Observed in Norzagaray and other Bulacan municipalities

**Pay Rules**: Typically follows Special Non-Working rules (130% if working, 0% if not working)

---

## Islamic Holidays (To be declared)
The following holidays will be declared separately based on the Islamic calendar:
- **Eidul Fitr** (Feast of Ramadhan) - Date TBD
- **Eidul Adha** (Feast of Sacrifice) - Date TBD

These are typically declared as **Special Non-Working Holidays**.

---

## Total Count by Type

- **Regular Holidays**: 10
- **Special Non-Working Holidays**: 8
- **Special Working Holiday**: 1
- **Local Special Holidays**: 2
- **Total**: 21 holidays (excluding Islamic holidays to be declared)

---

## Sources

1. **Proclamation No. 727** - Signed by Executive Secretary Lucas Bersamin on October 30, 2024
   - Declares regular holidays and special non-working days for 2025
   - Source: Presidential Communications Office

2. **Norzagaray Municipal Government**
   - August 13 Foundation Day information
   - Source: centralluzon.politiko.com.ph, bulacan.gov.ph

3. **House Bill No. 4395**
   - Proposed bill to declare August 13 as special non-working holiday
   - Status: Not yet enacted
   - Source: docs.congress.hrep.online

---

## Important Notes

1. **August 13 (Araw ng Norzagaray)**: While not officially a special non-working holiday, the local government suspends work and classes. Companies may choose to observe this as a local holiday.

2. **Islamic Holidays**: Dates for Eidul Fitr and Eidul Adha are determined by the Islamic calendar and will be declared separately by the government.

3. **Local Variations**: Some companies in Norzagaray may have additional company-specific holidays or may observe certain local festivals.

4. **Payroll Compliance**: Ensure your payroll system uses the correct multipliers:
   - Regular Holiday: 100% (not worked) / 200% (worked)
   - Special Non-Working: 0% (not worked) / 130% (worked)
   - Special Working: 100% (regular pay)
   - Local Special: Typically 0% (not worked) / 130% (worked)

---

## How to Import These Holidays

Use the provided `norzagaray_bulacan_holidays_2025.php` file:

```php
require_once 'norzagaray_bulacan_holidays_2025.php';
require_once 'config.php';

$result = importNorzagarayHolidays($conn);
echo $result['message'];
```

Or run from command line to view:
```bash
php norzagaray_bulacan_holidays_2025.php
```
