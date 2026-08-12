<?php

namespace App\Http\Controllers\Api\V1;

use OpenApi\Attributes as OA;

#[OA\Info(
    title: "ClassicPOS API",
    version: "1.0.0",
    description: "ClassicPOS — Point of Sale REST API. Multi-tenant SaaS with role-based access control.",
    contact: new OA\Contact(email: "support@oakitsolutionsandsupplies.com", name: "ClassicPOS Support")
)]
#[OA\Server(url: "/api/v1", description: "API v1")]
#[OA\Tag(name: "Auth", description: "Authentication & registration")]
#[OA\Tag(name: "Products", description: "Product management")]
#[OA\Tag(name: "Customers", description: "Customer management")]
#[OA\Tag(name: "POS", description: "Point of Sale operations")]
#[OA\Tag(name: "Sales", description: "Sale transactions")]
#[OA\Tag(name: "Cash Register", description: "Cash register shift management")]
#[OA\Tag(name: "Expenses", description: "Expense tracking")]
#[OA\Tag(name: "Returns", description: "Return & refund processing")]
#[OA\Tag(name: "Documents", description: "Quotes & invoices")]
#[OA\Tag(name: "Users", description: "User management")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    description: "Sanctum token from login endpoint"
)]
class OpenApiInfo {}
