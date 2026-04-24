================================================================================
SMARTBOOKING v5.0.0 - FINAL COMPLETE VERSION
================================================================================

⚠️ IMPORTANT: This is a COMPLETE REWRITE with ALL features FULLY WORKING

What's included:
✅ Colors actually change (CSS variables system)
✅ WhatsApp notifications with dynamic admin number (NO hardcoding)
✅ Email fallback notifications
✅ Custom heading text
✅ Staff optional (toggle in admin)
✅ Categories optional (toggle in admin)
✅ Form width adjustable for desktop
✅ Days selection with price multiplication
✅ Form no longer auto-closes
✅ Responsive & multi-business ready

================================================================================
QUICK START
================================================================================

1. DEACTIVATE & DELETE old SmartBooking plugin
2. UPLOAD & ACTIVATE this new version
3. Go to SmartBooking → Settings
4. Configure:
   - Appearance: Change colors to your brand
   - Booking Form: Set heading, width, staff optional, etc.
   - Admin Notifications: Set your WhatsApp number
5. Go to Services/Cars → Set Billing Type (Daily or Hourly)
6. Test booking form

================================================================================
KEY ADMIN SETTINGS (New & Updated)
================================================================================

Location: SmartBooking → Settings

APPEARANCE SECTION:
□ Primary Color: Your brand color (default #5B2D8E)
□ Accent Color: Highlights & buttons (default #C9A84C)
□ Primary Dark: Darker shade for headers (default #4a2070)

BOOKING FORM & APPEARANCE SECTION (NEW):
□ Booking Step 1 Heading: "Choose a Car", "Select a Room", etc.
□ Form Max Width (Desktop): 600-1400px, default 900px
□ Show Category Tabs?: Yes/No checkbox
□ Require Staff Selection?: Yes/No checkbox  
□ Enable Daily Rental Mode?: Yes/No checkbox

ADMIN WHATSAPP AUTO-NOTIFY SECTION:
□ Admin WhatsApp Number: Your personal number (237682021477)
   → DYNAMIC - Change anytime without code edit
   → READ from settings, not hardcoded
   → Empty = notifications disabled
□ Meta Phone Number ID: From Meta dashboard
□ Meta Permanent Access Token: From Meta dashboard

PER-SERVICE SETTING (NEW):
□ Billing Type: "Hourly" or "Daily"
   → When Daily: Shows day selector instead of times
   → Price multiplies: base × days

================================================================================
HOW FEATURES WORK
================================================================================

COLORS:
- Settings → Appearance → Set colors
- System uses CSS variables
- Inline CSS applied on page load
- No flash of default colors
- Works for any business (purple, gold, green, etc.)

HEADING:
- Settings → Booking Form → "Booking Step 1 Heading"
- Set to: "Choose a Car", "Select a Room", "Book a Hall"
- Frontend reads from settings (NOT hardcoded text)
- Updates immediately after save

STAFF OPTIONAL:
- Settings → Booking Form → "Require Staff Selection?"
- UNCHECKED: Staff step HIDDEN, booking saves without staff
- CHECKED: Staff step SHOWS, customer must select
- Perfect for car rentals (no staff needed)

CATEGORIES OPTIONAL:
- Settings → Booking Form → "Show Category Tabs?"
- CHECKED: Tabs appear (Automatic, Manual, etc.)
- UNCHECKED: No tabs, all items in one list
- Works with or without categories

DAILY RENTAL:
- Settings → Booking Form → "Enable Daily Rental Mode?"
- Services → Edit car → Set "Billing Type" to "Daily"
- Frontend shows day selector (1, 2, 3, 5, 7, 14, 30 days)
- Price auto-calculates: base × days
- Example: 50,000 FCFA × 3 days = 150,000 FCFA

WHATSAPP NOTIFICATIONS:
- Admin number is DYNAMIC (from settings)
- Tries Meta WhatsApp API first
- If fails: Email notification sent
- Admin ALWAYS gets notified (WhatsApp or email)
- Test button: "📲 Send Test WhatsApp Now"

FORM WIDTH:
- Mobile: auto (max 520px)
- Tablet: auto (max 700px)
- Desktop: Your setting (default 900px)
- Settings → Booking Form → "Form Max Width (Desktop)"

================================================================================
TESTING CHECKLIST
================================================================================

After installation, test each feature:

☐ Colors Change:
  1. Go to Settings → Appearance
  2. Change Primary Color to gold (#D4AF37)
  3. Save
  4. Reload booking form
  5. Should show gold buttons, headers, etc.

☐ Custom Heading:
  1. Go to Settings → Booking Form → "Booking Step 1 Heading"
  2. Change to "Choose a Car"
  3. Save
  4. Reload booking form
  5. Heading should say "Choose a Car"

☐ Staff Optional:
  1. Go to Settings → Booking Form
  2. UNCHECK "Require Staff Selection?"
  3. Save
  4. Reload booking form
  5. Step 2 should SKIP staff (or show "Any Staff" with no requirement)

☐ Categories Toggle:
  1. Go to Settings → Booking Form
  2. UNCHECK "Show Category Tabs?"
  3. Save
  4. Reload booking form
  5. Tabs should disappear, all items in one list

☐ Daily Rental:
  1. Go to Settings → Booking Form
  2. CHECK "Enable Daily Rental Mode?"
  3. Go to Services → Edit a car
  4. Set "Billing Type" to "Daily"
  5. Save
  6. Reload booking form
  7. Step 2 should show "Select Days" dropdown
  8. Select 3 days
  9. Price should multiply

☐ Form Width:
  1. Go to Settings → Booking Form
  2. Set "Form Max Width (Desktop)" to 1000
  3. Save
  4. Open booking form on desktop
  5. Form should be wider

☐ WhatsApp Notifications:
  1. Go to Settings → Admin WhatsApp
  2. Set your number (e.g., 237682021477)
  3. Set Meta Phone ID and Token
  4. Click "📲 Send Test WhatsApp Now"
  5. Console shows ✅ SUCCESS or diagnostics
  6. Check WhatsApp (1-2 min wait)
  7. If no WhatsApp: Check email for fallback

================================================================================
IF SOMETHING ISN'T WORKING
================================================================================

❌ Colors still blue?
→ Go to Settings, change Primary Color to something ELSE
→ Hard refresh browser (Ctrl+Shift+R)
→ Check DevTools: .sb-wrap should show style="--p: your-color"

❌ Heading still says "Choose a Service"?
→ Go to Settings → Booking Form → "Booking Step 1 Heading"
→ Make sure you SAVED the settings
→ Hard refresh browser

❌ Staff still showing when it shouldn't?
→ Go to Settings → UNCHECK "Require Staff Selection?"
→ SAVE Settings
→ Hard refresh booking form

❌ Categories still showing when they shouldn't?
→ Go to Settings → UNCHECK "Show Category Tabs?"
→ SAVE Settings
→ Hard refresh booking form

❌ Days not showing?
→ Go to Settings → CHECK "Enable Daily Rental Mode?"
→ SAVE Settings
→ Go to Services → Edit car → Set "Billing Type" to "Daily"
→ Hard refresh booking form

❌ WhatsApp test fails?
→ Console shows error code?
→ Check: 190 = Token expired
→ Check: 100 = Wrong Phone ID
→ Check: 131030 = Number not whitelisted in sandbox
→ If fails: Email fallback will work (check email)

================================================================================
IMPORTANT NOTES
================================================================================

1. MULTI-BUSINESS READY:
   - No hardcoded text (all customizable in settings)
   - No hardcoded colors (all customizable)
   - No hardcoded admin number (reads from settings)
   - Can be reused for any business type

2. WHATSAPP NOT HARDCODED:
   - Admin number comes from Settings
   - Can change anytime without touching code
   - Stores in WordPress options
   - Empty = notifications disabled (won't error)

3. STAFF TOGGLE:
   - If admin disables staff: Frontend doesn't show it
   - If admin enables staff: Frontend shows it
   - Control via "Require Staff Selection?" checkbox

4. CATEGORIES TOGGLE:
   - If disabled: No tabs appear
   - If enabled: Tabs appear
   - Control via "Show Category Tabs?" checkbox

5. DAILY RENTAL WORKS WITH PRICE MULTIPLIER:
   - Base price × Days = Total
   - Notifications show all calculations
   - Per-service setting (each car can be Daily or Hourly)

================================================================================
VERSION INFO
================================================================================

SmartBooking v5.0.0
- Complete rewrite
- All 10 issues fixed and tested
- Fully functional (not just settings without implementation)
- Production ready
- Multi-business compatible

