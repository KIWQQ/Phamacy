# Pharmacy POS - Static Version for Vercel

## 📋 Project Overview

This is a fully functional, static HTML/CSS/JavaScript version of the Pharmacy POS system designed for UI/UX design portfolio submission on Vercel. It requires no backend server, database, or PHP - everything runs entirely in the browser with mock data.

## ✨ Features Implemented

### 1. **Dashboard**
- Total Sales KPI
- Total Orders count
- Low Stock Items alert
- Recent orders table
- Low stock medicines list with status indicators

### 2. **New Order - Core Flow** ✅
- Customer selection with real-time search
- Medicine search and quick add functionality
- Dynamic order items management with +/- quantity controls
- Auto-calculated subtotal and total
- Payment method selection (Cash/Transfer)
- Success message upon save

### 3. **Allergy Detection & Prevention** 🔴
- **Prominent Red Warning Bar** when medicine conflicts with customer allergies
- **Automatic allergy display** when customer is selected
- **Prevents order save** until allergenic items are removed
- **One-click removal** of allergenic items via "Remove Allergenic Items" button
- Clear recovery flow allowing users to adjust order and retry

### 4. **Stock Validation & Worst Case State**
- **Live stock checking** as medicines are added
- **Yellow warning alert** shows when stock is insufficient
- **Detailed error messaging** includes:
  - **What**: Item name and quantity mismatch
  - **Why**: "Insufficient stock in inventory to fulfill this order"
  - **Next Step**: "Reduce quantity, change medicine, or remove items from order"
- **Recovery State** allows user to adjust and retry saving

### 5. **Confirmation Dialogs**
- Clear Order button → Confirmation modal
- Remove Allergenic Items → Toast notification
- Professional, accessible modal design

### 6. **All Navigation Pages**
- Orders List - View all saved orders
- Medicines Inventory - Browse all medicines with stock status
- Stock Levels - Detailed stock and reorder level tracking
- Customers - View all customers and their allergies
- Refunds - Placeholder for future implementation

## 🎨 UI/UX Design Highlights

- **Professional gradient backgrounds** with smooth animations
- **Glassmorphism effects** on cards and sidebar
- **High contrast typography** for excellent readability
- **Mobile-first responsive design**
  - Desktop: 2-column layout for new order
  - Tablet: Optimized spacing
  - Mobile: Single column, touch-friendly buttons
- **Consistent color scheme**:
  - Primary: #10b981 (Green) - Success
  - Danger: #ef4444 (Red) - Allergies/Errors
  - Warning: #f59e0b (Orange) - Stock issues
- **Accessibility features**:
  - WCAG compliant color contrast
  - Keyboard navigation support
  - Focus indicators
  - Screen reader friendly structure

## 📱 Responsive Design Breakpoints

```
Desktop (1024px+):  2-column order layout, full sidebar visible
Tablet (768px):    Single column, collapsible sidebar, optimized spacing
Mobile (480px):    Touch-friendly buttons, full-width inputs, stacked layout
```

## 🗂️ File Structure

```
vercel-version/
├── index.html       # Main HTML structure with all pages
├── style.css        # Complete styling with responsive design
├── script.js        # Application logic and mock data
└── README.md        # This file
```

## 🚀 How to Run

1. **Local Development:**
   ```bash
   # Option 1: Use Python's built-in server
   python -m http.server 8000
   # Visit: http://localhost:8000/index.html
   
   # Option 2: Use Node.js http-server
   npx http-server
   # Visit: http://localhost:8080
   ```

2. **On Vercel:**
   - Push files to GitHub repository
   - Connect to Vercel
   - Vercel automatically deploys static files

3. **Direct File Access:**
   - Simply open `index.html` in a modern web browser

## 📊 Mock Data Structure

### Customers (8 customers)
- Names, phone numbers, addresses
- Allergy information (some with multiple allergies)

### Medicines (12 medicines)
- Names, prices, stock levels
- Reorder levels for low stock alerts

### Orders (3 sample orders)
- Complete transaction history
- Used for dashboard KPI calculations

### Employees (3 employees)
- Optional selection during order creation

## 🔧 Key JavaScript Functions

```javascript
// Navigation
app.goToPage('dashboard|neworder|orders|medicines|stock|customers')

// Order Management
app.selectCustomer(customer)
app.addMedicineToOrder(medicine)
app.removeItemFromOrder(index)
app.updateItemQuantity(index, quantity)
app.saveOrder()

// Allergy & Stock Checking
app.checkAllergies()
app.checkStockAndAllergies()
app.clearAllergyItems()

// UI Interactions
app.showModal(title, message, onConfirm)
app.showToast(message)
```

## ⚠️ Allergy Prevention Flow

1. User selects customer → Allergies displayed
2. If medicine added matches allergy → RED ALERT shown
3. User cannot save until allergenic items removed
4. "Remove Allergenic Items" button clears conflicting medicines
5. Order can then be saved successfully

## 📈 Stock Validation Flow

1. User adds medicine → Stock checked
2. If quantity > available → YELLOW WARNING shown
3. Error details: What/Why/Next Step
4. User can adjust quantity or remove items
5. Save succeeds when stock is sufficient

## 🎯 User Testing Scenarios

### Scenario 1: Normal Order
1. Select customer without allergies
2. Add medicines
3. Review total
4. Save order
5. Success message shown

### Scenario 2: Allergy Conflict
1. Select "นาย สมชาย ใจดี" (allergic to: ยาแก้แพ้, เพนิซิลิน)
2. Try adding "เพนิซิลิน 500mg"
3. RED ALERT appears
4. Click "Remove Allergenic Items"
5. Alert clears
6. Save order successfully

### Scenario 3: Stock Issue
1. Add "ยาแก้แพ้ Cetirizine" with quantity 10 (only 5 available)
2. YELLOW WARNING appears
3. Reduce quantity to 5
4. Warning disappears
5. Save order successfully

## 🌙 Dark Mode

- Toggle available via button in sidebar footer
- Preference saved to localStorage
- Automatically applies on page reload

## 📈 Performance

- Pure HTML/CSS/JavaScript (no frameworks)
- No external dependencies except Bootstrap Icons
- Lightweight mock data stored in memory
- Instant page transitions
- <100KB total file size

## 🔐 Security Notes

- No sensitive data transmission
- Client-side processing only
- No cookies or authentication required
- Safe for public deployment

## 📝 Assignment Completion Checklist

- ✅ HTML, CSS, Vanilla JavaScript only
- ✅ No PHP or backend connection
- ✅ No MySQL or database
- ✅ Mock data embedded in script.js
- ✅ Opens from index.html
- ✅ Relative paths only
- ✅ All buttons functional
- ✅ Readable UI (good contrast, appropriate font sizes)
- ✅ Error messages with What/Why/Next Step
- ✅ Desktop responsive design (1024px+)
- ✅ Mobile responsive design (480px+)
- ✅ Sidebar navigation (Dashboard, Orders, Medicines, Stock, Customers)
- ✅ Dashboard with sales/orders/low stock KPIs
- ✅ New Order page as core flow
- ✅ Customer selection from mock data
- ✅ Medicine search and add
- ✅ Quantity adjustment (+/-)
- ✅ Auto calculation (Subtotal/Total)
- ✅ Save Order with success message
- ✅ **Allergy detection with red warning bar**
- ✅ **Prevent save until allergies removed**
- ✅ **Stock checking with worst case state**
- ✅ **Recovery state for adjustment**
- ✅ **Confirmation dialogs for actions**
- ✅ **Error messages with reasons and solutions**

## 🎨 Design Inspiration

Based on the original PHP Pharmacy POS system, this static version maintains:
- Professional healthcare industry aesthetics
- Clean, modern UI patterns
- Efficient workflow design
- Accessibility best practices

## 🚀 Future Enhancements

- LocalStorage to persist orders (optional)
- Print functionality for receipts
- Medicine images
- Advanced search filters
- Discount calculations
- Multiple payment methods with details

## 📞 Support

All functionality is self-contained in three files:
1. `index.html` - Structure
2. `style.css` - Styling
3. `script.js` - Logic & Data

For modifications, edit the respective files and refresh the browser.

---

**Ready for Vercel Deployment** ✨  
All files are ready to be pushed to a GitHub repository and deployed to Vercel with zero configuration needed.
