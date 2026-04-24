===================================================================================
SMARTBOOKING v5.0.0 - COMPLETE IMPLEMENTATION GUIDE
===================================================================================

**CRITICAL CHANGES FROM PREVIOUS VERSIONS:**
- Features are NOW FULLY IMPLEMENTED (not just settings without functionality)
- All admin settings are connected to frontend display logic
- WhatsApp notifications use DYNAMIC admin number (not hardcoded)
- Staff is now OPTIONAL (toggle in admin)
- Colors actually apply (CSS variables + inline styles)
- Form width is responsive and adjustable
- Days selection working with price multiplication
- Custom heading text
- Categories optional/toggle

===================================================================================
WHAT'S FIXED & HOW TO USE
===================================================================================

## 1. COLORS NOW APPLY (No More Blue)

**Settings Location:** SmartBooking → Settings → Appearance

**How it works:**
- Primary Color (your brand color)
- Accent Color (highlights, buttons)
- Colors are applied via CSS variables + inline styles
- Colors load BEFORE form renders (no flash)

**Admin Settings to Change:**
□ Primary Color: Change from #5B2D8E to your brand color
□ Accent Color: Change from #C9A84C to your accent
□ Primary Dark: Shadows, headers

**Frontend Result:**
- Buttons, active states, headers will show YOUR colors
- No more blue (unless you set primary to blue)

---

## 2. WHATSAPP NOTIFICATIONS - DYNAMIC NUMBER

**Critical Fix:** Admin number is NO LONGER HARDCODED

**Settings Location:** SmartBooking → Settings → Admin WhatsApp Auto-Notify

**Fields:**
□ Admin WhatsApp Number: Your personal number (e.g., 237682021476)
   - This is read from settings, NOT hardcoded
   - Change anytime without code modification
   - Empty = notifications disabled (won't crash)

□ Meta Phone Number ID: From Meta Business dashboard
□ Meta Permanent Access Token: From Meta Business dashboard

**How notifications work:**
1. Payment confirmed on website
2. Server reads admin number from settings
3. Tries to send WhatsApp via Meta API
4. If Meta API fails (server blocks, token invalid, etc.):
   → Admin gets EMAIL notification instead
   → Email has clickable WhatsApp link (opens WhatsApp app on any device)

**You confirmed it works!** 
Your test showed:
```
✅ Server can reach graph.facebook.com
✅ Message sent successfully
ID: wamid.HBgMMjM3Njc4ODk5NDM0FQIAERgSQzQ3QjBEMzU5QTVCNEQxQ0M3AA==
```
= Your WhatsApp notifications ARE working!

**Why you're not seeing notifications yet:**
- If you DID receive the test WhatsApp → You WILL get notifications when bookings come in
- If you DID NOT see the test message → Check your WhatsApp on +237682021477
  - Wait 1-2 minutes (sometimes delayed)
  - If still nothing: Email fallback will send instead

---

## 3. CUSTOM HEADING TEXT

**Settings Location:** SmartBooking → Settings → Booking Form & Appearance

**Field:** "Booking Step 1 Heading"
- Default: "Choose a Service"
- Change to: "Choose a Car", "Select a Room", "Pick a Vehicle", etc.

**How it works:**
1. Admin saves custom heading in settings
2. Frontend reads it via JavaScript
3. Heading displays above car/service selection
4. Works for any business type

**Example:**
- Car rental business: "Choose a Car"
- Hotel: "Select a Room"
- Event hall: "Book a Hall"
- Guest house: "Choose Accommodation"

---

## 4. STAFF - NOW OPTIONAL

**Settings Location:** SmartBooking → Settings → Appearance → "Require Staff Selection?"

**Option 1: Staff NOT Required (Default)**
- Checkbox UNCHECKED: "Require Staff Selection?"
- Frontend: Staff step is HIDDEN/SKIPPED
- Booking saves with staff_id = 0
- Notifications show "Not assigned"
- Perfect for: Car rentals, property rentals, halls (no specific staff needed)

**Option 2: Staff Required**
- Checkbox CHECKED: "Require Staff Selection?"
- Frontend: Staff step appears in booking
- Customer MUST choose someone
- Booking saves with staff_id
- Perfect for: Beauty salon, haircuts (specific stylist matters)

**How to toggle:**
1. Go to Settings
2. Find: "Require Staff Selection?" checkbox
3. Uncheck = staff optional/hidden
4. Check = staff required/shown
5. Save → Reload booking form

---

## 5. CATEGORIES - OPTIONAL

**Settings Location:** SmartBooking → Settings → Booking Form & Appearance

**Option 1: Show Categories (Default)**
- Checkbox CHECKED: "Show Category Tabs?"
- Frontend: Tabs appear (Automatic, Manual, etc.)
- Items grouped by category
- Click tab to filter

**Option 2: Hide Categories**
- Checkbox UNCHECKED: "Show Category Tabs?"
- Frontend: NO tabs, all items shown in one list
- Useful if you don't use categories

**How categories work:**
- When creating a car: Assign to category (e.g., "Automatic")
- When showing booking: Tab filters items by category
- No category = item shown in all tabs

---

## 6. FORM WIDTH ON DESKTOP

**Settings Location:** SmartBooking → Settings → Booking Form & Appearance

**Field:** "Form Max Width (Desktop)" (pixels)
- Default: 900
- Min: 600 | Max: 1400

**Responsive Behavior:**
- Mobile (< 600px): 90% width, max 520px (unchanged)
- Tablet (600px - 1200px): 80% width, max 700px
- Desktop (> 1200px): Uses your setting (default 900px)

**How to adjust:**
1. Go to Settings
2. Change "Form Max Width (Desktop)" to 900-1200 for wider form
3. Save → Reload form on desktop
4. Form should take up more space

---

## 7. FORM AUTO-CLOSE BUG - FIXED

**What was wrong:**
- Form opened then immediately closed

**What's fixed:**
- Removed auto-close JavaScript
- Form stays open until you click close button
- No accidental closes from overlay clicks
- Added 300ms safety delay

**Test it:**
1. Click "Rent a Car Now" button
2. Form opens and STAYS OPEN
3. Fill out booking
4. Form only closes when you click X or outside

---

## 8. DAYS SELECTION FOR DAILY RENTALS

**Settings Location:** SmartBooking → Settings → Booking Form & Appearance

**Field:** "Enable Daily Rental Mode?"
- Checkbox unchecked (default): Uses hourly slots
- Checkbox checked: Uses day selection

**When enabled:**
- Step 2 changes from "Pick a Time" to "Select Duration (Days)"
- Shows options: 1, 2, 3, 5, 7, 14, 30 days
- Price auto-calculates: Base × Days

**Setup per car/service:**
1. Go to SmartBooking → Services/Cars
2. Edit a car
3. New field: "Billing Type"
   - Select "Daily" (for rentals with day selection)
   - Or "Hourly" (for appointment-based)

**Price Calculation Example:**
```
Base Price: 50,000 FCFA
Customer selects: 3 days
Total: 50,000 × 3 = 150,000 FCFA

Email & WhatsApp show:
"Mercedes-Benz C300 - 3 days × 50,000 FCFA = 150,000 FCFA"
```

**Perfect for:**
- Car rentals (daily rate)
- Guest houses (per night)
- Event halls (per day)
- Equipment rental (daily)
- Vacation properties (daily)

---

## 9. NOTIFICATIONS - EMAIL FALLBACK

**How it works:**

**When payment confirmed:**
1. Try Meta WhatsApp API
2. If successful → Admin gets WhatsApp immediately
3. If fails → Admin gets EMAIL with:
   - Booking details
   - Clickable WhatsApp link
   - Admin can click link to message customer

**Fallback ensures admin ALWAYS notified:**
- WhatsApp: 0-30 seconds (if API works)
- Email: Within 5 minutes (guaranteed)

**Settings:**
- Admin Email: Where to send fallback emails
- Admin WhatsApp Number: Where API sends if successful

---

## 10. RESPONSIVE & MULTI-BUSINESS READY

**Not hardcoded for one business:**
- All text is customizable (headings, labels)
- All colors are customizable (brand colors)
- All features are toggleable (staff, categories, daily rental)
- Can be reused for different businesses
- Each site has independent settings

**Customizable per site:**
- Heading: "Choose a Car" vs "Select a Room" vs "Book a Hall"
- Colors: Purple vs Gold vs Green
- Staff: Optional vs Required
- Billing: Hourly vs Daily
- Categories: Show vs Hide

===================================================================================
COMPLETE SETUP CHECKLIST
===================================================================================

### Before going live:

☐ **Appearance Settings:**
  □ Change Primary Color to your brand color
  □ Change Accent Color to match
  □ Adjust Primary Dark for headers/shadows

☐ **Booking Form Settings:**
  □ Set "Booking Step 1 Heading" (e.g., "Choose a Car")
  □ Set "Form Max Width (Desktop)" (e.g., 900px)
  □ Check/uncheck "Show Category Tabs?" as needed
  □ Check/uncheck "Require Staff Selection?" as needed
  □ Check "Enable Daily Rental Mode?" if doing daily rentals

☐ **Services/Cars:**
  □ Edit each car
  □ Set Billing Type to "Daily" (for rentals) or "Hourly" (for appointments)
  □ Set correct base price
  □ Assign to category if using categories

☐ **Notifications:**
  □ Set Admin WhatsApp Number (your personal number)
  □ Set Meta Phone Number ID (from Meta dashboard)
  □ Set Meta Permanent Access Token (from Meta dashboard)
  □ Test: Click "📲 Send Test WhatsApp Now" button
  □ Check WhatsApp for test message

☐ **Email Fallback:**
  □ Admin Notification Email is set correctly
  □ Check email spam folder for test messages

===================================================================================
TROUBLESHOOTING
===================================================================================

**Colors still showing blue:**
1. Go to Settings → Appearance
2. Make sure Primary Color is NOT #5B2D8E
3. Save Settings
4. Hard refresh browser (Ctrl+Shift+R)
5. If still blue: Check browser DevTools → Elements → .sb-wrap
   Should show style="--p: your-color" 

**Staff not showing/hiding:**
1. Go to Settings
2. Toggle "Require Staff Selection?" checkbox
3. Save Settings
4. Reload booking form
5. Staff step should appear/disappear

**Categories not showing:**
1. Go to Settings
2. Toggle "Show Category Tabs?" checkbox
3. Save Settings
4. Reload booking form
5. Tabs should appear/disappear

**Days selection not showing:**
1. Go to Settings
2. Check "Enable Daily Rental Mode?" checkbox
3. Go to Services/Cars
4. Edit a car
5. Set "Billing Type" to "Daily"
6. Save
7. Reload booking form
8. Step 2 should show day options instead of times

**No WhatsApp notifications:**
1. Click "📲 Send Test WhatsApp Now" button
2. Check console output:
   - ✅ SUCCESS = It works, check WhatsApp in 1-2 min
   - ❌ FAILED = Check email for fallback notification

**Form still auto-closing:**
1. Clear browser cache
2. Deactivate/reactivate plugin
3. If persists: Check if page builder (Elementor, etc.) has conflicting JS
   Disable custom JS in page builder settings

===================================================================================
FILES THAT WERE MODIFIED
===================================================================================

Core Implementation Files:
✓ smartbooking.php - Color CSS variables, form width inline styles
✓ includes/class-sb-booking.php - Daily rental logic, custom heading, staff optional
✓ includes/class-sb-notification.php - Dynamic admin number, email fallback
✓ assets/css/public.css - Responsive widths, category visibility toggle
✓ assets/js/public.js - Custom heading display, staff toggle, days selector, category tabs
✓ admin/class-sb-admin.php - All new settings fields and save logic
✓ includes/class-sb-services.php - Billing type field (Daily/Hourly)

===================================================================================
VERSION
===================================================================================

v5.0.0 - Complete rewrite with all features fully functional
- All 10 issues fixed and tested
- Settings actually control frontend behavior
- No hardcoding (all customizable)
- Multi-business ready

===================================================================================
