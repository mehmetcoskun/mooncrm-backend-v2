<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerFileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\MetaWebhookController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailTemplateController;
use App\Http\Controllers\PartnerHotelController;
use App\Http\Controllers\PartnerTransferController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SegmentController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StatisticController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\SmsController;
use App\Http\Controllers\SmsTemplateController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TwoFactorAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VapiController;
use App\Http\Controllers\WebFormController;
use App\Http\Controllers\WhatsappSessionController;
use App\Http\Controllers\WhatsappTemplateController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\WorkScheduleController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/two-factor/verify', [TwoFactorAuthController::class, 'verify']);

Route::prefix('/meta')->controller(MetaWebhookController::class)->group(function () {
    Route::get('/webhook', 'verify');
    Route::post('/webhook', 'webhook');
});

Route::prefix('/web-form')->controller(WebFormController::class)->group(function () {
    Route::prefix('/iframe')->group(function () {
        Route::get('/{uuid}', 'iframe');
        Route::post('/{uuid}', 'submit');
    });
});

Route::post('/vapi/webhook', [VapiController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);

    Route::prefix('/auth/two-factor')->controller(TwoFactorAuthController::class)->group(function () {
        Route::post('/qr-code', 'generateQrCode');
        Route::post('/enable', 'enable');
        Route::post('/disable', 'disable');
        Route::get('/status', 'status');
        Route::post('/recovery-codes', 'regenerateRecoveryCodes');
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::prefix('/setting')->controller(SettingController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'updateOrCreate');
        Route::post('/verify-mail', 'verifyMail');
    });

    Route::prefix('/organization')->controller(OrganizationController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{organization}', 'show');
        Route::put('/{organization}', 'update');
        Route::delete('/{organization}', 'destroy');
    });

    Route::prefix('/customer')->controller(CustomerController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::post('/bulk-delete', 'bulkDelete');
        Route::post('/bulk-update-status', 'bulkUpdateStatus');
        Route::post('/bulk-update-category', 'bulkUpdateCategory');
        Route::post('/bulk-update-user', 'bulkUpdateUser');
        Route::get('/{customer}', 'show');
        Route::put('/{customer}', 'update');
        Route::delete('/{customer}', 'destroy');
        Route::get('/segment-filter/{segment}', 'segmentFilter');
        Route::get('/logs/{customer}', 'logs');
        Route::post('/webhook', 'webhook');

        Route::prefix('/{customer}/file')->controller(CustomerFileController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{customerFile}', 'update');
            Route::delete('/{customerFile}', 'destroy');
        });
    });

    Route::prefix('/whatsapp')->group(function () {
        Route::prefix('/session')->controller(WhatsappSessionController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{whatsappSession}', 'update');
            Route::delete('/{whatsappSession}', 'destroy');
        });

        Route::prefix('/template')->controller(WhatsappTemplateController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{whatsappTemplate}', 'update');
            Route::delete('/{whatsappTemplate}', 'destroy');
        });
    });

    Route::get('/statistics', [StatisticController::class, 'getStatistics']);

    Route::get('/report', [ReportController::class, 'getReports']);

    Route::prefix('/segment')->controller(SegmentController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{segment}', 'show');
        Route::put('/{segment}', 'update');
        Route::delete('/{segment}', 'destroy');
        Route::get('/filter/{segment}', 'filter');
    });

    Route::prefix('/template')->group(function () {
        Route::prefix('/email')->controller(EmailTemplateController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::get('/{emailTemplate}', 'show');
            Route::put('/{emailTemplate}', 'update');
            Route::delete('/{emailTemplate}', 'destroy');
        });

        Route::prefix('/sms')->controller(SmsTemplateController::class)->group(function () {
            Route::get('/', 'index');
            Route::post('/', 'store');
            Route::put('/{smsTemplate}', 'update');
            Route::delete('/{smsTemplate}', 'destroy');
        });
    });

    Route::prefix('/marketing')->group(function () {
        Route::prefix('/email')->group(function () {
            Route::post('/send', [EmailController::class, 'send']);
            Route::post('/bulk-send', [EmailController::class, 'bulkSend']);
        });

        Route::prefix('/sms')->group(function () {
            Route::post('/send', [SmsController::class, 'send']);
            Route::post('/bulk-send', [SmsController::class, 'bulkSend']);
        });
    });

    Route::prefix('/web-form')->controller(WebFormController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::get('/{webForm}', 'show');
        Route::put('/{webForm}', 'update');
        Route::delete('/{webForm}', 'destroy');
    });

    Route::prefix('/user')->controller(UserController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{user}', 'update');
        Route::delete('/{user}', 'destroy');
        Route::get('/{user}/tokens', 'tokens');
        Route::post('/{user}/tokens', 'createToken');
        Route::delete('/{user}/tokens/{token}', 'destroyToken');
    });

    Route::prefix('/role')->controller(RoleController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{role}', 'update');
        Route::delete('/{role}', 'destroy');
    });

    Route::prefix('/permission')->controller(PermissionController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{permission}', 'update');
        Route::delete('/{permission}', 'destroy');
        Route::get('/available-permissions', 'getAvailablePermissions');
    });

    Route::prefix('/category')->controller(CategoryController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{category}', 'update');
        Route::delete('/{category}', 'destroy');
    });

    Route::prefix('/service')->controller(ServiceController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{service}', 'update');
        Route::delete('/{service}', 'destroy');
    });

    Route::prefix('/status')->controller(StatusController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{status}', 'update');
        Route::delete('/{status}', 'destroy');
    });

    Route::prefix('/tag')->controller(TagController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{tag}', 'update');
        Route::delete('/{tag}', 'destroy');
    });

    Route::prefix('/doctor')->controller(DoctorController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{doctor}', 'update');
        Route::delete('/{doctor}', 'destroy');
    });

    Route::prefix('/partner-hotel')->controller(PartnerHotelController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{partnerHotel}', 'update');
        Route::delete('/{partnerHotel}', 'destroy');
    });

    Route::prefix('/partner-transfer')->controller(PartnerTransferController::class)->group(function () {
        Route::get('/', 'index');
        Route::post('/', 'store');
        Route::put('/{partnerTransfer}', 'update');
        Route::delete('/{partnerTransfer}', 'destroy');
    });

    Route::get('/appointment', [AppointmentController::class, 'getAppointments']);

    Route::prefix('/work-schedule')->controller(WorkScheduleController::class)->group(function () {
        Route::get('/', 'index');
        Route::get('/working-status', 'getWorkingStatus');
    });
});
