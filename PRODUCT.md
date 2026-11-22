# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Stack

Plain PHP 8 + MySQL/MariaDB, no framework and no Composer, so it runs as-is in XAMPP (Apache + phpMyAdmin) on Windows. The client chose this; it matches the project proposal (Chapter 1, Table 1.4: Windows 11, VS Code, phpMyAdmin, MySQL, XAMPP, PHP, Chrome). PDO with prepared statements, a small front-controller with light MVC layering, server-rendered HTML, progressive-enhancement JavaScript only.

## Users

- **Customers (members):** mostly women, many of them university students around Rangsit, renting an outfit for a specific date: graduation (รับปริญญา), weddings and engagement parties, evening events, Thai-dress occasions, and themed parties. They browse on their phones, care first about whether a dress is free on their date and fits their size, then the price, deposit and how to pay.
- **Guests (ผู้ใช้งานทั่วไป):** visitors who are not logged in. They browse the catalogue and check availability, then register to book.
- **Staff (พนักงาน):** shop-floor work: costumes, categories, bookings, pick-up and return, checking payment slips, receipts and reports.
- **Administrator (ผู้ดูแลระบบ):** the owner. Has every staff power plus member management (suspend or reactivate, change roles) and shop settings.

## Product Purpose

A web application for the Panteen Rent dress-rental shop (case study for a university project titled “การพัฒนาเว็บแอปพลิเคชันร้านเช่าชุด กรณีศึกษาร้าน Panteen Rent”). Today the shop runs on Instagram, LINE OA chat and handwritten notes, so data is scattered, double bookings and forgotten return dates happen, and rent, deposit and fines are worked out by hand. The system centralises costumes, customers, bookings, payments and returns so that customers can see what is free on their date 24 hours a day and book it themselves, and the shop never double-books a dress.

Success means:
1. A customer can go from “I need a dress for 14 Dec” to a confirmed, paid booking without chatting to the shop.
2. No dress can be booked twice for overlapping dates, including its cleaning buffer.
3. Staff can see today’s pick-ups and returns, verify slips, and close a return with any fine computed automatically.
4. Reports summarise rentals, money and appointments for any period.

The project also evaluates system quality and user satisfaction (5-level Likert scale, descriptive statistics), so the system must be complete and demonstrable.

## Positioning

The only booking site built around this one shop’s real rhythm: dates come first. Every costume shows a live availability calendar that already accounts for pick-up, return and cleaning time, and booking, QR PromptPay payment, slip upload, reminders and receipts happen in one flow. It is not a multi-shop marketplace.

## Operating Context

- Customers use phones over mobile data; staff use a Windows PC or laptop at the counter, and receipts are printed.
- Payment is by PromptPay QR plus uploading a transfer slip, which staff verify by hand. There is no payment gateway.
- Email is used for registration verification, password reset, booking status and pick-up/return reminders. SMTP is configurable. Without SMTP, mail is saved to an in-app development mailbox so demos work offline.
- Rental lifecycle: pending payment → slip awaiting check → confirmed → picked up (on rent) → returned (with fines) → completed; or cancelled or rejected.
- Costume inventory states: available, booked, on rent, cleaning, maintenance or retired.

## Capabilities and Constraints

The proposal’s Table 1.1 function list governs scope (11 groups): login/logout; registration, password reset and forgot password, email verification; member data (view, edit, suspend); costume management (add, edit, availability status); costume categories (add, edit, view, delete); user management (choose costume and dates, edit, view, change status); notifications (appointment scheduling, pick-up/return reminders); booking management (rented list, booking detail, cancellation); reports (rentals, finance, appointments); payment (status, QR code, slip upload); receipts (view, print).

Client decision: members book online themselves (resolving the proposal’s contradiction), and admin/staff can also book at the counter for a customer.

Constraints: single shop only; sample data only (no real customer data); delivery/shipping is out of scope; Thai-language UI.

## Brand Commitments

- Name: “Panteen Rent” (from the proposal; spelled “panteen rent” in places, “Panteen Rant” is a typo).
- Women’s dress rental. The client asked for imagery sourced from the internet (free-licence stock) because no shop photography or logo was supplied.
- The client explicitly forbids an “AI slop” look and wants the most beautiful design possible.

## Evidence on Hand

- Proposal slides: `~/Downloads/การพัฒนาเว็ปแอปพลิเคชั่นร้านเช่าชุด.pdf`
- An earlier, separate student project for the same shop (Next.js back-office) at `/Volumes/UNITEK/university/freelances/projects/OrderForm`: its domain model and fine logic are reference only.
- No real logo, shop photos, prices, testimonials, address, bank or PromptPay details were supplied. All shop settings, prices and sample bookings are demonstration data, editable in settings and labelled as samples where a visitor could mistake them for real ones. Never invent reviews, ratings, customer counts or awards.

## Product Principles

1. **Date first, always.** Every browsing and booking decision is anchored to the customer’s event date and availability.
2. **No double booking, ever.** Availability is enforced in the database transaction, not just in the UI.
3. **Money is explicit.** Rent, deposit, fines and refunds are itemised the same way on screen, in email and on the printed receipt.
4. **The counter is fast.** Staff screens favour scanability and few clicks for the daily pick-up and return run.
5. **Honest demo.** Sample data is labelled as samples and nothing is fabricated as real.

## Accessibility & Inclusion

Thai-first typography with correct line height for Thai vowels and tone marks; WCAG 2.2 AA contrast; full keyboard operation; touch targets of at least 44px on the customer side; reduced-motion respected.
