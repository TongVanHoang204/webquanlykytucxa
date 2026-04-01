# Auth Two-Column Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild `login.php` and `register.php` into a shared two-column auth experience with a warm premium poster panel on the left and a focused form panel on the right, without changing auth business logic.

**Architecture:** Keep `assets/css/auth_shell.css` as the shared warm visual foundation for existing auth pages, and add a new isolated split-layout stylesheet used only by `login.php` and `register.php`. Implement the redesign by refactoring each page into the same `auth-shell--split` markup contract, then protect that contract with a lightweight Node smoke test and PHP lint/manual browser verification.

**Tech Stack:** PHP, HTML, CSS, Font Awesome, Node.js (`assert` + `fs`) for static smoke checks, PHP CLI lint

---

## File Structure

- Create: `assets/css/auth_split.css`
  Responsibility: shared two-column layout, poster panel, form panel, and responsive behavior used only by login/register.
- Create: `tests/auth-layout.test.mjs`
  Responsibility: static smoke checks for the new auth layout contract so future edits do not silently collapse the split shell.
- Modify: `login.php`
  Responsibility: adopt shared split-shell markup and login-specific poster copy while preserving current login logic and validation.
- Modify: `register.php`
  Responsibility: adopt shared split-shell markup and register-specific poster copy while preserving current registration logic and validation.
- Optional Modify: `package.json`
  Responsibility: add a dedicated script for the auth layout smoke test if the worker wants a shorter command.

## Task 1: Build The Shared Split Shell For Login

**Files:**
- Create: `assets/css/auth_split.css`
- Create: `tests/auth-layout.test.mjs`
- Modify: `login.php`
- Optional Modify: `package.json`

- [ ] **Step 1: Write the failing smoke test for the login split shell**

```js
import assert from 'node:assert/strict';
import { existsSync, readFileSync } from 'node:fs';

function read(filePath) {
  return readFileSync(filePath, 'utf8');
}

function expectIncludes(filePath, needle) {
  const content = read(filePath);
  assert.ok(
    content.includes(needle),
    `Expected ${filePath} to include: ${needle}`
  );
}

assert.ok(
  existsSync('assets/css/auth_split.css'),
  'Expected assets/css/auth_split.css to exist'
);

expectIncludes('login.php', 'assets/css/auth_split.css');
expectIncludes('login.php', 'class="auth-shell auth-shell--split auth-shell--login"');
expectIncludes('login.php', 'class="auth-poster"');
expectIncludes('login.php', 'class="auth-panel"');
expectIncludes('login.php', 'Đăng ký phòng');
expectIncludes('login.php', 'Theo dõi hồ sơ');
expectIncludes('assets/css/auth_split.css', '.auth-shell--split');
expectIncludes('assets/css/auth_split.css', '.auth-poster');
expectIncludes('assets/css/auth_split.css', '.auth-panel');
expectIncludes('assets/css/auth_split.css', '@media (max-width: 960px)');

console.log('auth split layout smoke test passed');
```

- [ ] **Step 2: Run the smoke test and verify it fails for the expected reason**

Run: `node tests/auth-layout.test.mjs`

Expected: FAIL with `Expected assets/css/auth_split.css to exist`

- [ ] **Step 3: Write the minimal implementation for the shared CSS and login markup**

Create `assets/css/auth_split.css` with a namespaced split-shell layout so it does not change `forgot_password.php` or `reset_password.php`:

```css
.auth-shell--split {
    max-width: 1180px !important;
}

.auth-shell--split .auth-card {
    display: grid;
    grid-template-columns: minmax(0, 1.12fr) minmax(380px, 0.88fr);
    gap: 0;
    padding: 0 !important;
    overflow: hidden;
}

.auth-shell--split .auth-card::before {
    inset: 0 !important;
    background:
        radial-gradient(circle at top left, rgba(212, 160, 97, 0.18), transparent 38%),
        linear-gradient(135deg, rgba(17, 32, 47, 0.02), rgba(17, 32, 47, 0.08)) !important;
}

.auth-poster {
    position: relative;
    padding: 44px 42px;
    background:
        radial-gradient(circle at top left, rgba(255, 255, 255, 0.2), transparent 24%),
        linear-gradient(160deg, rgba(182, 124, 63, 0.92), rgba(120, 78, 37, 0.88));
    color: #fff7ef;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    min-height: 100%;
}

.auth-poster__eyebrow {
    margin: 0 0 18px;
    font-size: 0.76rem;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: rgba(255, 247, 239, 0.78);
}

.auth-poster__title {
    margin: 0;
    font-family: 'Cormorant Garamond', Georgia, serif;
    font-size: clamp(2.6rem, 4vw, 4rem);
    line-height: 0.96;
    letter-spacing: -0.04em;
}

.auth-poster__lead {
    margin: 18px 0 0;
    max-width: 34rem;
    font-size: 1rem;
    line-height: 1.7;
    color: rgba(255, 247, 239, 0.88);
}

.auth-benefits {
    list-style: none;
    padding: 0;
    margin: 30px 0 0;
    display: grid;
    gap: 16px;
}

.auth-benefits li {
    display: grid;
    grid-template-columns: 42px 1fr;
    gap: 14px;
    align-items: start;
}

.auth-benefits__icon {
    width: 42px;
    height: 42px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 247, 239, 0.14);
    color: #fff7ef;
}

.auth-benefits__body strong {
    display: block;
    margin-bottom: 4px;
    font-size: 0.98rem;
}

.auth-benefits__body span {
    font-size: 0.9rem;
    line-height: 1.6;
    color: rgba(255, 247, 239, 0.8);
}

.auth-poster__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    margin-top: 34px;
}

.auth-poster__meta span {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    border-radius: 999px;
    background: rgba(255, 247, 239, 0.14);
    color: rgba(255, 247, 239, 0.88);
    font-size: 0.84rem;
}

.auth-panel {
    position: relative;
    padding: 34px 32px 28px;
    background: rgba(255, 251, 246, 0.92);
}

.auth-panel__brand {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
    color: #6c5441;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
}

.auth-panel__brand::before {
    content: "";
    width: 28px;
    height: 1px;
    background: rgba(108, 84, 65, 0.45);
}

.auth-panel .auth-header {
    text-align: left !important;
    margin-bottom: 18px !important;
}

.auth-panel .auth-header h2 {
    justify-content: flex-start !important;
}

.auth-panel .auth-footer {
    margin-top: 18px;
}

@media (max-width: 960px) {
    .auth-shell--split {
        max-width: 720px !important;
    }

    .auth-shell--split .auth-card {
        grid-template-columns: 1fr;
    }

    .auth-poster,
    .auth-panel {
        padding: 30px 24px;
    }
}

@media (max-width: 520px) {
    .auth-poster__title {
        font-size: 2.4rem;
    }

    .auth-poster__lead,
    .auth-benefits__body span {
        font-size: 0.92rem;
    }

    .auth-poster__meta {
        gap: 8px;
    }

    .auth-poster__meta span {
        width: 100%;
        justify-content: center;
    }

    .auth-panel {
        padding: 24px 18px 20px;
    }
}
```

In `login.php`, add the new stylesheet in the `<head>` after `assets/css/auth_shell.css`:

```php
  <link rel="stylesheet" href="assets/css/auth_shell.css">
  <link rel="stylesheet" href="assets/css/auth_split.css">
```

Replace the current `<body>` auth block with the split-shell structure while keeping the existing form controls, alerts, token field, JS, and submit logic:

```php
  <div class="auth-wrapper auth-shell auth-shell--split auth-shell--login">
    <div class="auth-card animate-fadeIn">
      <section class="auth-poster" aria-hidden="true">
        <div>
          <p class="auth-poster__eyebrow">Cổng sinh viên ký túc xá</p>
          <h1 class="auth-poster__title">Theo dõi lưu trú trong một không gian tập trung.</h1>
          <p class="auth-poster__lead">
            Đăng ký phòng, kiểm tra hồ sơ, quản lý hóa đơn và nhận thông báo mới trong cùng một hệ thống dành cho sinh viên nội trú.
          </p>

          <ul class="auth-benefits">
            <li>
              <span class="auth-benefits__icon"><i class="fa-solid fa-bed"></i></span>
              <div class="auth-benefits__body">
                <strong>Đăng ký phòng</strong>
                <span>Gửi yêu cầu ở ký túc xá và theo dõi tiến độ xử lý rõ ràng.</span>
              </div>
            </li>
            <li>
              <span class="auth-benefits__icon"><i class="fa-solid fa-address-card"></i></span>
              <div class="auth-benefits__body">
                <strong>Theo dõi hồ sơ</strong>
                <span>Quản lý thông tin sinh viên và các bước hoàn thiện hồ sơ trên cùng một màn hình.</span>
              </div>
            </li>
            <li>
              <span class="auth-benefits__icon"><i class="fa-solid fa-bell"></i></span>
              <div class="auth-benefits__body">
                <strong>Hóa đơn và thông báo</strong>
                <span>Nhận nhắc việc, cập nhật trạng thái và kiểm tra các khoản cần xử lý đúng hạn.</span>
              </div>
            </li>
          </ul>
        </div>

        <div class="auth-poster__meta">
          <span><i class="fa-solid fa-shield-heart"></i> Truy cập tập trung</span>
          <span><i class="fa-solid fa-receipt"></i> Theo dõi minh bạch</span>
          <span><i class="fa-solid fa-bolt"></i> Xử lý nhanh</span>
        </div>
      </section>

      <section class="auth-panel">
        <div class="auth-panel__brand">Ký túc xá sinh viên</div>

        <div class="auth-header">
          <h2><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập hệ thống</h2>
          <p>Tiếp tục thao tác đang dở và quản lý toàn bộ thông tin lưu trú của bạn.</p>
        </div>

        <?php if (!empty($error)): ?>
          <div class="alert alert-error animate-shakeX">
            <div class="alert-title">
              <i class="fa-solid fa-triangle-exclamation"></i> Không thể đăng nhập
            </div>
            <p><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
          </div>
        <?php endif; ?>

        <form id="loginForm" method="POST" novalidate>
          <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

          <div class="form-row">
            <div class="input-group">
              <label for="username">Tên đăng nhập</label>
              <div class="input-with-icon">
                <i class="fa-solid fa-user"></i>
                <input
                  type="text"
                  id="username"
                  name="username"
                  value="<?= htmlspecialchars($oldUsername, ENT_QUOTES, 'UTF-8') ?>"
                  required
                  placeholder="Nhập tên đăng nhập"
                >
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="input-group">
              <label for="password">Mật khẩu</label>
              <div class="input-with-icon password-wrapper">
                <i class="fa-solid fa-lock"></i>
                <input type="password" id="password" name="password" required placeholder="Nhập mật khẩu">
                <button type="button" class="toggle-password" data-target="password">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>

              <div class="login-meta">
                <label class="remember-me">
                  <input type="checkbox" id="remember" name="remember">
                  <span>Ghi nhớ đăng nhập</span>
                </label>
                <a href="forgot_password.php" class="forgot-link">
                  <i class="fa-solid fa-key"></i> Quên mật khẩu?
                </a>
              </div>
            </div>
          </div>

          <div id="clientErrorBox" class="alert alert-error hidden">
            <div class="alert-title">
              <i class="fa-solid fa-triangle-exclamation"></i> Vui lòng kiểm tra lại
            </div>
            <ul class="alert-list" id="clientErrorList"></ul>
          </div>

          <button type="submit" class="btn-primary">
            <i class="fa-solid fa-right-to-bracket"></i> Đăng nhập
          </button>

          <div class="social-divider">
            <span></span>
            <p>Hoặc đăng nhập nhanh bằng</p>
            <span></span>
          </div>

          <div class="social-buttons">
            <a href="http://localhost/oauth_google.php" class="btn-social btn-google">
              <i class="fa-brands fa-google"></i>
              Google
            </a>
          </div>

          <p class="auth-footer">
            Chưa có tài khoản?
            <a href="register.php"><i class="fa-solid fa-user-plus"></i> Đăng ký ngay</a>
          </p>
        </form>
      </section>
    </div>
  </div>
```

If a shorter command is preferred, add this script in `package.json`:

```json
{
  "scripts": {
    "test:auth-layout": "node tests/auth-layout.test.mjs"
  }
}
```

- [ ] **Step 4: Run the smoke test and verify it passes**

Run: `node tests/auth-layout.test.mjs`

Expected: PASS with `auth split layout smoke test passed`

- [ ] **Step 5: Commit the login shell baseline**

```bash
git add assets/css/auth_split.css tests/auth-layout.test.mjs login.php package.json
git commit -m "feat: add split auth shell for login"
```

If `package.json` was not changed, omit it from `git add`.

## Task 2: Extend The Shared Shell To Register

**Files:**
- Modify: `tests/auth-layout.test.mjs`
- Modify: `register.php`

- [ ] **Step 1: Extend the smoke test with register-specific assertions**

Update `tests/auth-layout.test.mjs` by appending these checks after the existing login assertions:

```js
expectIncludes('register.php', 'assets/css/auth_split.css');
expectIncludes('register.php', 'class="auth-shell auth-shell--split auth-shell--register"');
expectIncludes('register.php', 'class="auth-poster"');
expectIncludes('register.php', 'class="auth-panel"');
expectIncludes('register.php', 'Tạo tài khoản sinh viên');
expectIncludes('register.php', 'Tạo tài khoản');
expectIncludes('register.php', 'Đăng ký phòng');
```

- [ ] **Step 2: Run the smoke test and verify it fails because register has not been migrated**

Run: `node tests/auth-layout.test.mjs`

Expected: FAIL with `Expected register.php to include: class="auth-shell auth-shell--split auth-shell--register"`

- [ ] **Step 3: Write the minimal register implementation using the same shell contract**

In `register.php`, add the new stylesheet in the `<head>` after `assets/css/auth_shell.css`:

```php
    <link rel="stylesheet" href="assets/css/auth_shell.css">
    <link rel="stylesheet" href="assets/css/auth_split.css">
```

Replace the current auth block with the split-shell structure while preserving the existing hidden token, validation messages, password strength logic, password match logic, social login button, and submit behavior:

```php
    <div class="auth-wrapper auth-shell auth-shell--split auth-shell--register">
        <div class="auth-card animate-fadeIn">
            <section class="auth-poster" aria-hidden="true">
                <div>
                    <p class="auth-poster__eyebrow">Khởi tạo tài khoản lưu trú</p>
                    <h1 class="auth-poster__title">Tạo tài khoản sinh viên để bắt đầu toàn bộ quy trình nội trú.</h1>
                    <p class="auth-poster__lead">
                        Một tài khoản duy nhất để đăng ký phòng, cập nhật hồ sơ, theo dõi hóa đơn và nhận thông báo chính thức từ hệ thống.
                    </p>

                    <ul class="auth-benefits">
                        <li>
                            <span class="auth-benefits__icon"><i class="fa-solid fa-file-signature"></i></span>
                            <div class="auth-benefits__body">
                                <strong>Tạo tài khoản</strong>
                                <span>Bắt đầu đúng quy trình với thông tin sinh viên được lưu trữ tập trung.</span>
                            </div>
                        </li>
                        <li>
                            <span class="auth-benefits__icon"><i class="fa-solid fa-bed"></i></span>
                            <div class="auth-benefits__body">
                                <strong>Đăng ký phòng</strong>
                                <span>Chuyển nhanh sang yêu cầu ở ký túc xá ngay sau khi hoàn tất tài khoản.</span>
                            </div>
                        </li>
                        <li>
                            <span class="auth-benefits__icon"><i class="fa-solid fa-envelope-open-text"></i></span>
                            <div class="auth-benefits__body">
                                <strong>Nhận thông báo</strong>
                                <span>Theo dõi các cập nhật hồ sơ, hóa đơn và trạng thái xử lý trong cùng một nơi.</span>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="auth-poster__meta">
                    <span><i class="fa-solid fa-user-check"></i> Hồ sơ tập trung</span>
                    <span><i class="fa-solid fa-building-user"></i> Quy trình rõ ràng</span>
                    <span><i class="fa-solid fa-clock"></i> Theo dõi liên tục</span>
                </div>
            </section>

            <section class="auth-panel">
                <div class="auth-panel__brand">Ký túc xá sinh viên</div>

                <div class="auth-header">
                    <h2><i class="fa-solid fa-user-plus"></i> Tạo tài khoản sinh viên</h2>
                    <p>Tạo tài khoản để bắt đầu đăng ký nội trú và quản lý các tác vụ học kỳ trong một hệ thống duy nhất.</p>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert alert-error animate-shakeX">
                        <div class="alert-title"><i class="fa-solid fa-triangle-exclamation"></i> Có lỗi xảy ra</div>
                        <ul class="alert-list">
                            <?php foreach ($errors as $e): ?>
                                <li><?= htmlspecialchars($e, ENT_QUOTES, 'UTF-8') ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success)): ?>
                    <div class="alert alert-success animate-slideIn">
                        <div class="alert-title"><i class="fa-solid fa-circle-check"></i> Thành công</div>
                        <p><?= $success ?></p>
                    </div>
                <?php endif; ?>

                <form id="registerForm" method="POST" novalidate>
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="form-row">
                        <div class="input-group">
                            <label for="fullname">Họ và tên</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-id-card"></i>
                                <input
                                    type="text"
                                    id="fullname"
                                    name="fullname"
                                    value="<?= htmlspecialchars($old['fullname'], ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    minlength="3"
                                    placeholder="Nguyễn Văn A"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="email">Email</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-envelope"></i>
                                <input
                                    type="email"
                                    id="email"
                                    name="email"
                                    value="<?= htmlspecialchars($old['email'], ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    placeholder="email@sv.truong.edu.vn"
                                >
                            </div>
                        </div>

                        <div class="input-group">
                            <label for="phone">Số điện thoại</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-phone"></i>
                                <input
                                    type="tel"
                                    id="phone"
                                    name="phone"
                                    value="<?= htmlspecialchars($old['phone'], ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    placeholder="0xxxxxxxxx"
                                >
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="username">Tên đăng nhập</label>
                            <div class="input-with-icon">
                                <i class="fa-solid fa-user"></i>
                                <input
                                    type="text"
                                    id="username"
                                    name="username"
                                    value="<?= htmlspecialchars($old['username'], ENT_QUOTES, 'UTF-8') ?>"
                                    required
                                    minlength="4"
                                    maxlength="20"
                                    placeholder="username_ktx"
                                >
                            </div>
                            <small class="hint">4–20 ký tự, chỉ gồm chữ, số, dấu chấm và gạch dưới.</small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="password">Mật khẩu</label>
                            <div class="input-with-icon password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="password" name="password" required>
                                <button type="button" class="toggle-password" data-target="password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <div class="password-strength">
                                <div class="strength-bar" id="passwordStrengthBar"></div>
                            </div>
                            <small class="hint">
                                Ít nhất 8 ký tự, gồm chữ hoa, chữ thường và số.
                            </small>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label for="confirm_password">Xác nhận mật khẩu</label>
                            <div class="input-with-icon password-wrapper">
                                <i class="fa-solid fa-lock"></i>
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="toggle-password" data-target="confirm_password">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <small id="matchMessage" class="hint"></small>
                        </div>
                    </div>

                    <div id="clientErrorBox" class="alert alert-error hidden">
                        <div class="alert-title"><i class="fa-solid fa-triangle-exclamation"></i> Vui lòng kiểm tra lại</div>
                        <ul class="alert-list" id="clientErrorList"></ul>
                    </div>

                    <button type="submit" class="btn-primary">
                        <i class="fa-solid fa-paper-plane"></i> Đăng ký
                    </button>

                    <div class="social-divider">
                        <span></span>
                        <p>Hoặc đăng ký nhanh bằng</p>
                        <span></span>
                    </div>

                    <div class="social-buttons">
                        <a href="http://localhost:8080/oauth_google.php" class="btn-social btn-google">
                            <i class="fa-brands fa-google"></i>
                            Google
                        </a>
                    </div>

                    <p class="auth-footer">
                        Đã có tài khoản?
                        <a href="login.php"><i class="fa-solid fa-right-to-bracket"></i> Đăng nhập</a>
                    </p>
                </form>
            </section>
        </div>
    </div>
```

Place the `email` and `phone` fields together on the same `.form-row` for desktop pairing, and keep `fullname`, `username`, `password`, and `confirm_password` each on their own full-width rows.

- [ ] **Step 4: Run the smoke test and verify it passes**

Run: `node tests/auth-layout.test.mjs`

Expected: PASS with `auth split layout smoke test passed`

- [ ] **Step 5: Commit the register migration**

```bash
git add register.php tests/auth-layout.test.mjs
git commit -m "feat: migrate register page to split auth shell"
```

## Task 3: Final Verification And Regression Guardrails

**Files:**
- Modify: `tests/auth-layout.test.mjs`
- Verify: `login.php`
- Verify: `register.php`
- Verify: `forgot_password.php`
- Verify: `reset_password.php`

- [ ] **Step 1: Add isolation assertions so the new layout stays scoped to login/register**

Append these checks to `tests/auth-layout.test.mjs`:

```js
const forgotPassword = read('forgot_password.php');
const resetPassword = read('reset_password.php');

assert.ok(
  !forgotPassword.includes('assets/css/auth_split.css'),
  'forgot_password.php should not load auth_split.css'
);

assert.ok(
  !resetPassword.includes('assets/css/auth_split.css'),
  'reset_password.php should not load auth_split.css'
);
```

- [ ] **Step 2: Run the smoke test and verify it stays green**

Run: `node tests/auth-layout.test.mjs`

Expected: PASS with `auth split layout smoke test passed`

- [ ] **Step 3: Run PHP syntax checks for the affected auth pages**

Run:

```bash
php -l login.php
php -l register.php
php -l forgot_password.php
php -l reset_password.php
```

Expected: each command prints `No syntax errors detected in ...`

- [ ] **Step 4: Manually verify the responsive UI in the browser**

Open:

```text
http://localhost/WEBQuanLyKyTucXa/login.php
http://localhost/WEBQuanLyKyTucXa/register.php
```

Check:

- desktop shows a clear two-column poster + form composition
- mobile stacks poster above form without clipped content
- login still shows remembered values and validation alerts correctly
- register still shows password strength, password match, and server/client error blocks correctly
- forgot/reset pages still look unchanged

- [ ] **Step 5: Commit the regression guardrails and verification-backed finish**

```bash
git add tests/auth-layout.test.mjs login.php register.php assets/css/auth_split.css
git commit -m "test: add auth layout regression checks"
```

## Self-Review

### Spec Coverage

- Shared two-column shell: covered by Task 1 CSS + login markup and Task 2 register markup.
- Poster + form hierarchy: covered by both page markup tasks.
- Login/register message differences: covered by page-specific poster and form copy in Tasks 1 and 2.
- Responsive behavior: covered by `assets/css/auth_split.css` media queries in Task 1 and browser checks in Task 3.
- Preservation of current auth behavior: covered by explicit instruction in Tasks 1 and 2 to keep form logic and validation intact.
- Isolation from other auth pages: covered by Task 3 smoke-test assertions and forgot/reset manual verification.

### Placeholder Scan

- No `TODO`, `TBD`, or "similar to previous task" placeholders remain.
- Each command and file path is explicit.
- Every code-writing step includes concrete code to add.

### Type And Naming Consistency

- Shared class contract is consistent across tasks: `auth-shell--split`, `auth-poster`, `auth-panel`.
- Page modifiers are consistent across tasks: `auth-shell--login`, `auth-shell--register`.
- Smoke test file path remains consistent: `tests/auth-layout.test.mjs`.
