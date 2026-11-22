<?php
declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\AuthController;
use App\Controllers\BookingController;
use App\Controllers\DevController;
use App\Controllers\ShopController;
use App\Controllers\Admin;
use App\Core\Router;

$r = new Router();

/* Storefront (guests welcome) */
$r->get('/', [ShopController::class, 'home']);
$r->get('/dresses', [ShopController::class, 'index']);
$r->get('/c/{slug}', [ShopController::class, 'category']);
$r->get('/dress/{code}', [ShopController::class, 'show']);
$r->get('/api/dress/{code}/calendar', [ShopController::class, 'calendar']);
$r->get('/api/dress/{code}/quote', [ShopController::class, 'quote']);
$r->get('/how-to-rent', [ShopController::class, 'howTo']);
$r->get('/credits', [ShopController::class, 'credits']);

/* Auth */
$r->get('/login', [AuthController::class, 'loginForm'], ['guest']);
$r->post('/login', [AuthController::class, 'login'], ['guest']);
$r->get('/register', [AuthController::class, 'registerForm'], ['guest']);
$r->post('/register', [AuthController::class, 'register'], ['guest']);
$r->post('/logout', [AuthController::class, 'logout']);
$r->get('/verify/{token}', [AuthController::class, 'verify']);
$r->get('/account/verify', [AuthController::class, 'verifyNotice'], ['auth']);
$r->post('/account/verify', [AuthController::class, 'resendVerification'], ['auth']);
$r->get('/forgot', [AuthController::class, 'forgotForm'], ['guest']);
$r->post('/forgot', [AuthController::class, 'forgot'], ['guest']);
$r->get('/reset/{token}', [AuthController::class, 'resetForm']);
$r->post('/reset/{token}', [AuthController::class, 'reset']);

/* Member account */
$r->get('/account', [AccountController::class, 'profile'], ['auth']);
$r->post('/account/profile', [AccountController::class, 'updateProfile'], ['auth']);
$r->post('/account/measurements', [AccountController::class, 'updateMeasurements'], ['auth']);
$r->post('/account/password', [AccountController::class, 'updatePassword'], ['auth']);
$r->get('/account/favorites', [AccountController::class, 'favorites'], ['auth']);
$r->get('/account/addresses', [AccountController::class, 'addresses'], ['auth']);
$r->post('/account/addresses', [AccountController::class, 'saveAddress'], ['auth']);
$r->post('/account/addresses/{id}/default', [AccountController::class, 'defaultAddress'], ['auth']);
$r->post('/account/addresses/{id}/delete', [AccountController::class, 'deleteAddress'], ['auth']);
$r->post('/favorites/{code}', [AccountController::class, 'toggleFavorite'], ['auth']);
$r->get('/account/notifications', [AccountController::class, 'notifications'], ['auth']);
$r->post('/account/notifications/read', [AccountController::class, 'readNotifications'], ['auth']);
$r->get('/account/appointments', [AccountController::class, 'appointments'], ['auth']);
$r->post('/account/appointments', [AccountController::class, 'requestAppointment'], ['verified']);
$r->post('/account/appointments/{id}/cancel', [AccountController::class, 'cancelAppointment'], ['auth']);
$r->get('/api/slots', [AccountController::class, 'slots']);

/* Booking, payment, receipts */
$r->get('/book/{code}', [BookingController::class, 'create'], ['verified']);
$r->post('/book/{code}', [BookingController::class, 'store'], ['verified']);
$r->get('/bookings', [BookingController::class, 'index'], ['auth']);
$r->get('/bookings/{id}', [BookingController::class, 'show'], ['auth']);
$r->post('/bookings/{id}/slip', [BookingController::class, 'uploadSlip'], ['auth']);
$r->post('/bookings/{id}/cancel', [BookingController::class, 'cancel'], ['auth']);
$r->get('/receipts/{id}', [BookingController::class, 'receipt'], ['auth']);
$r->get('/slips/{id}', [BookingController::class, 'slip'], ['auth']);
$r->post('/bookings/{id}/received', [BookingController::class, 'received'], ['auth']);
$r->post('/bookings/{id}/arrival', [BookingController::class, 'arrival'], ['auth']);
$r->post('/bookings/{id}/post-back', [BookingController::class, 'postBack'], ['auth']);
$r->post('/bookings/{id}/dispute', [BookingController::class, 'dispute'], ['auth']);
$r->get('/condition/{id}', [BookingController::class, 'photo'], ['auth']);

/* Simulated mailbox (dev mode, or admins) */
$r->get('/dev/mailbox', [DevController::class, 'mailbox']);
$r->get('/dev/mailbox/{id}', [DevController::class, 'mail']);

/* Back office: staff + admin */
$r->get('/admin', [Admin\DashboardController::class, 'index'], ['staff']);
$r->get('/admin/notifications', [Admin\DashboardController::class, 'notifications'], ['staff']);

$r->get('/admin/bookings', [Admin\BookingsController::class, 'index'], ['staff']);
$r->get('/admin/bookings/new', [Admin\BookingsController::class, 'create'], ['staff']);
$r->post('/admin/bookings', [Admin\BookingsController::class, 'store'], ['staff']);
$r->get('/admin/bookings/{id}', [Admin\BookingsController::class, 'show'], ['staff']);
$r->post('/admin/bookings/{id}/pay', [Admin\BookingsController::class, 'payCounter'], ['staff']);
$r->post('/admin/bookings/{id}/pickup', [Admin\BookingsController::class, 'pickup'], ['staff']);
$r->post('/admin/bookings/{id}/return', [Admin\BookingsController::class, 'markReturned'], ['staff']);
$r->post('/admin/bookings/{id}/cancel', [Admin\BookingsController::class, 'cancel'], ['staff']);
$r->post('/admin/bookings/{id}/reschedule', [Admin\BookingsController::class, 'reschedule'], ['staff']);
$r->post('/admin/bookings/{id}/ship', [Admin\FulfilmentController::class, 'ship'], ['staff']);
$r->post('/admin/bookings/{id}/delivered', [Admin\FulfilmentController::class, 'delivered'], ['staff']);
$r->post('/admin/bookings/{id}/failed', [Admin\FulfilmentController::class, 'failed'], ['staff']);
$r->post('/admin/bookings/{id}/to-pickup', [Admin\FulfilmentController::class, 'toPickup'], ['staff']);
$r->post('/admin/bookings/{id}/post-back', [Admin\FulfilmentController::class, 'postBack'], ['staff']);
$r->post('/admin/bookings/{id}/receive', [Admin\FulfilmentController::class, 'receive'], ['staff']);
$r->post('/admin/bookings/{id}/return-lost', [Admin\FulfilmentController::class, 'returnLost'], ['staff']);
$r->post('/admin/bookings/{id}/photos', [Admin\FulfilmentController::class, 'photos'], ['staff']);
$r->post('/admin/bookings/{id}/dispute', [Admin\FulfilmentController::class, 'dispute'], ['staff']);
$r->get('/admin/bookings/{id}/inspect', [Admin\FulfilmentController::class, 'inspectForm'], ['staff']);
$r->post('/admin/bookings/{id}/inspect', [Admin\FulfilmentController::class, 'inspect'], ['staff']);
$r->get('/admin/bookings/{id}/label', [Admin\FulfilmentController::class, 'label'], ['staff']);
$r->get('/admin/shipping', [Admin\FulfilmentController::class, 'queue'], ['staff']);
$r->get('/admin/scan', [Admin\FulfilmentController::class, 'scan'], ['staff']);
$r->get('/admin/costumes/{id}/tag', [Admin\FulfilmentController::class, 'tag'], ['staff']);
$r->get('/admin/damage-types', [Admin\FulfilmentController::class, 'damageTypes'], ['staff']);
$r->post('/admin/damage-types', [Admin\FulfilmentController::class, 'damageTypeSave'], ['admin']);
$r->get('/admin/schedule', [Admin\BookingsController::class, 'schedule'], ['staff']);
$r->get('/admin/api/members', [Admin\MembersController::class, 'lookup'], ['staff']);

$r->get('/admin/payments', [Admin\PaymentsController::class, 'index'], ['staff']);
$r->post('/admin/payments/{id}/approve', [Admin\PaymentsController::class, 'approve'], ['staff']);
$r->post('/admin/payments/{id}/reject', [Admin\PaymentsController::class, 'reject'], ['staff']);

$r->get('/admin/costumes', [Admin\CostumesController::class, 'index'], ['staff']);
$r->get('/admin/costumes/new', [Admin\CostumesController::class, 'create'], ['staff']);
$r->post('/admin/costumes', [Admin\CostumesController::class, 'store'], ['staff']);
$r->get('/admin/costumes/{id}', [Admin\CostumesController::class, 'edit'], ['staff']);
$r->post('/admin/costumes/{id}', [Admin\CostumesController::class, 'update'], ['staff']);
$r->post('/admin/costumes/{id}/status', [Admin\CostumesController::class, 'status'], ['staff']);
$r->post('/admin/costumes/{id}/images', [Admin\CostumesController::class, 'addImage'], ['staff']);
$r->post('/admin/costumes/{id}/images/{img}/delete', [Admin\CostumesController::class, 'deleteImage'], ['staff']);
$r->post('/admin/costumes/{id}/images/{img}/cover', [Admin\CostumesController::class, 'coverImage'], ['staff']);

$r->get('/admin/categories', [Admin\CategoriesController::class, 'index'], ['staff']);
$r->post('/admin/categories', [Admin\CategoriesController::class, 'store'], ['staff']);
$r->post('/admin/categories/{id}', [Admin\CategoriesController::class, 'update'], ['staff']);
$r->post('/admin/categories/{id}/delete', [Admin\CategoriesController::class, 'delete'], ['staff']);

$r->get('/admin/members', [Admin\MembersController::class, 'index'], ['staff']);
$r->get('/admin/members/new', [Admin\MembersController::class, 'create'], ['staff']);
$r->post('/admin/members', [Admin\MembersController::class, 'store'], ['staff']);
$r->get('/admin/members/{id}', [Admin\MembersController::class, 'show'], ['staff']);
$r->post('/admin/members/{id}', [Admin\MembersController::class, 'update'], ['staff']);
$r->post('/admin/members/{id}/status', [Admin\MembersController::class, 'status'], ['admin']);
$r->post('/admin/members/{id}/role', [Admin\MembersController::class, 'role'], ['admin']);

$r->get('/admin/appointments', [Admin\AppointmentsController::class, 'index'], ['staff']);
$r->post('/admin/appointments', [Admin\AppointmentsController::class, 'store'], ['staff']);
$r->post('/admin/appointments/{id}/status', [Admin\AppointmentsController::class, 'status'], ['staff']);

$r->get('/admin/reports', [Admin\ReportsController::class, 'index'], ['staff']);
$r->get('/admin/reports/{type}', [Admin\ReportsController::class, 'show'], ['staff']);

$r->get('/admin/settings', [Admin\SettingsController::class, 'index'], ['admin']);
$r->post('/admin/settings', [Admin\SettingsController::class, 'update'], ['admin']);
$r->post('/admin/settings/test-mail', [Admin\SettingsController::class, 'testMail'], ['admin']);
$r->post('/admin/settings/reseed', [Admin\SettingsController::class, 'reseed'], ['admin']);

return $r;
