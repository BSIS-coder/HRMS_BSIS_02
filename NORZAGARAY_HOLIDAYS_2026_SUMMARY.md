# Holidays for Municipality of Norzagaray, Bulacan - 2026

## Summary

Based on **Proclamation No. 1006** signed by President Ferdinand R. Marcos Jr. on September 3, 2025, here are the holidays observed in Norzagaray, Bulacan for 2026:

## Regular Holidays (10 holidays)
These are nationwide regular holidays that apply to all municipalities including Norzagaray:

1. **New Year's Day** - January 1 (Thursday)
2. **Maundy Thursday** - April 2
3. **Good Friday** - April 3
4. **Araw ng Kagitingan** (Day of Valor) - April 9 (Thursday)
5. **Labor Day** - May 1 (Friday)
6. **Independence Day** - June 12 (Friday)
7. **National Heroes Day** - August 31 (Last Monday of August)
8. **Bonifacio Day** - November 30 (Monday)
9. **Christmas Day** - December 25 (Friday)
10. **Rizal Day** - December 30 (Wednesday)

**Pay Rules**: 100% of daily wage if not working, 200% if working

---

## Special Non-Working Holidays (8 holidays)
These are nationwide special non-working holidays:

1. **Chinese New Year** - February 17 (Tuesday)
2. **Black Saturday** - April 4
3. **Ninoy Aquino Day** - August 21 (Friday)
4. **All Saints' Day** - November 1 (Sunday)
5. **All Souls' Day** - November 2 (Monday)
6. **Feast of the Immaculate Conception of Mary** - December 8 (Tuesday)
7. **Christmas Eve** - December 24 (Thursday)
8. **New Year's Eve** - December 31 (Thursday)

**Pay Rules**: No work, no pay (unless company policy says otherwise), 130% if working

---

## Special Working Holiday (1 holiday)
Work proceeds as usual:

1. **EDSA People Power Revolution Anniversary** - February 25 (Wednesday)

**Pay Rules**: Regular daily wage (100%)

---

## Local Special Holidays (2 holidays)
These are specific to Norzagaray, Bulacan and the province:

1. **Araw ng Norzagaray** (Norzagaray Foundation Day) - August 13 (Thursday)
   - **Note**: Not officially declared as a special non-working holiday by law
   - However, government work in municipal offices and classes in public schools are typically suspended
   - Celebrates the municipality's founding anniversary
   - Includes the Casay Festival
   - House Bill No. 4395 was introduced to make this a special non-working holiday, but not yet enacted

2. **Bulacan Province Founding Anniversary** - August 15 (Saturday)
   - Observed in Norzagaray and other Bulacan municipalities
   - Historically declared as a special non-working holiday in the province

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

## Key Changes from 2025 to 2026

1. **Maundy Thursday & Good Friday**: Moved from April 17-18 (2025) to April 2-3 (2026)
2. **Black Saturday**: Moved from April 19 (2025) to April 4 (2026)
3. **Chinese New Year**: Moved from January 29 (2025) to February 17 (2026)
4. **National Heroes Day**: Moved from August 25 (2025) to August 31 (2026) - still last Monday of August
5. **All Saints' Day Eve**: Removed from 2026 list (was October 31, 2025)
6. **All Souls' Day**: Added as separate holiday on November 2, 2026

---

## Sources

1. **Proclamation No. 1006** - Signed by President Ferdinand R. Marcos Jr. on September 3, 2025
   - Declares regular holidays and special non-working days for 2026
   - Source: elibrary.judiciary.gov.ph

2. **Norzagaray Municipal Government**
   - August 13 Foundation Day information
   - Source: centralluzon.politiko.com.ph, bulacan.gov.ph

3. **House Bill No. 4395**
   - Proposed bill to declare August 13 as special non-working holiday
   - Status: Not yet enacted
   - Source: docs.congress.hrep.online

---

## Important Notes

1. **August 13 (Araw ng Norzagaray)**: While not officially a special non-working holiday, the local government typically suspends work and classes. Companies may choose to observe this as a local holiday.

2. **Islamic Holidays**: Dates for Eidul Fitr and Eidul Adha are determined by the Islamic calendar and will be declared separately by the government based on recommendations from the National Commission on Muslim Filipinos.

3. **Local Variations**: Some companies in Norzagaray may have additional company-specific holidays or may observe certain local festivals.

4. **Payroll Compliance**: Ensure your payroll system uses the correct multipliers:
   - Regular Holiday: 100% (not worked) / 200% (worked)
   - Special Non-Working: 0% (not worked) / 130% (worked)
   - Special Working: 100% (regular pay)
   - Local Special: Typically 0% (not worked) / 130% (worked)

---

## How to Import These Holidays

Use the provided `norzagaray_bulacan_holidays_2026.php` file:

```php
require_once 'norzagaray_bulacan_holidays_2026.php';
require_once 'config.php';

$result = importNorzagarayHolidays2026($conn);
echo $result['message'];
```

Or run from command line to view:
```bash
php norzagaray_bulacan_holidays_2026.php
```

Or use the web import script:
- Visit: `import_norzagaray_holidays_2026.php` in your browser
