# GEMINI.md

## Project Overview
**Bees Fleur** is a comprehensive Florist Point of Sale (POS) and Stock Management system. It is designed to handle integrated cashier operations, stock management based on SKU, and delivery tracking specifically for a florist business.

### Core Technologies
- **Backend:** Laravel 12 (PHP 8.2+)
- **Frontend:** Vue 3 with Inertia.js
- **Styling:** Tailwind CSS (v4 integration via Vite plugin), Shadcn-style components
- **Auth & UI Starter:** Laravel Jetstream (Sanctum, Fortify)
- **Key Packages:**
  - `spatie/laravel-permission`: Role and permission management (Super Admin, Admin, Manager, Kasir).
  - `spatie/laravel-activitylog`: Comprehensive audit trails for data changes.
  - `spatie/laravel-medialibrary`: Media handling for products/orders.
  - `maatwebsite/excel`: Exporting reports.
  - `tightenco/ziggy`: Route sharing between Laravel and Vue.

### Architecture
- **Action Classes:** Complex business logic is extracted into Action classes (e.g., `app/Actions/CreateOrderAction.php`).
- **Strict Typing:** Every PHP file uses `declare(strict_types=1);`.
- **Domain Model:** Includes Customers, Orders, Order Details, Stock Movements, Bouquet Categories/Types/Units, Item Categories/Units, and Deliveries.

---

## Building and Running

### Prerequisites
- PHP 8.2+
- Node.js & NPM
- Composer
- SQLite (default) or other supported database

### Commands
- **Initial Setup:**
  ```bash
  composer run setup
  ```
  *(Installs PHP/JS dependencies, copies .env, generates keys, and runs migrations)*

- **Development:**
  ```bash
  composer run dev
  ```
  *(Runs Laravel server, Vite, Queue listener, and Pail logs concurrently)*

- **Testing:**
  ```bash
  composer run test
  ```

---

## Development Conventions

### Backend Rules (from `backend-rules.md`)
1. **Validation:** Always use **Form Request Validation** classes (`php artisan make:request`).
2. **Security:** Define `$fillable` in Models to prevent mass assignment vulnerabilities.
3. **Performance:** Use **Eager Loading** (`with()`) to solve N+1 query issues.
4. **Data Integrity:** Use `DB::transaction()` for multi-step database operations.
5. **Database:** Apply indexing in migrations for frequently searched/filtered columns.
6. **Concurrency:** Move heavy tasks (Email, Exports) to **Queues/Jobs**.
7. **Design Pattern:** Use **Action Classes** or Service Pattern for complex logic.
8. **Caching:** Use `Cache::remember()` for static/infrequently changed data.
9. **Types:** Maintain **Strict Typing** for parameters and return values.
10. **Maintenance:** Always use **SoftDeletes** and **Activity Log** for related models.

### Frontend Rules (from `frontend-rules.md`)
1. **Design:** Follow a **Mobile-First Approach** using Tailwind breakpoints.
2. **Theme:** Use **CSS Variables** in `app.css` for colors (Theme: Pastel Pink, White, Black). Avoid hardcoding colors.
3. **Components:** Extract UI elements into **Reusable Vue Components** (prefer Shadcn-style).
4. **UX:** Always include **Loading States** (spinners, skeleton loaders, disabled buttons) for async actions.
5. **Smoothness:** Use Tailwind **Transitions** for hover and state changes.
6. **Optimization:** Apply **Debouncing** (300-500ms) for live search inputs.

### Aesthetic Guidelines
- **Vibe:** Calm, comfortable, professional.
- **Visuals:** Pastel colors, soft shadows, subtle borders, large readable fonts.
- **Icons:** Minimalist and consistent with the florist theme.
