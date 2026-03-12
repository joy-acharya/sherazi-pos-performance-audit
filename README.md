# Sherazi POS Performance Audit

This repository contains a performance audit and optimization of the
Sherazi POS API built with Laravel.

The objective was to identify performance bottlenecks and implement
scalable solutions to improve API efficiency, reduce database load, and
enhance system scalability.

------------------------------------------------------------------------

# Project Overview

The system provides APIs for managing:

-   Products
-   Orders
-   Categories
-   Sales Reports
-   Dashboard Statistics

During the audit, several performance issues were identified including:

-   N+1 query problems
-   Unpaginated large responses
-   Missing database indexes
-   Repeated heavy queries without caching

These issues were resolved using Laravel best practices.

------------------------------------------------------------------------

# Performance Issues & Solutions

## 1. N+1 Query Problem

Several endpoints triggered multiple queries when loading relationships.

Example issues found in:

-   Sales report endpoint
-   Orders list
-   Product category loading

### Solution

Eager loading introduced:

    with()
    withCount()

Example:

``` php
Order::with('customer')
     ->withCount('items')
     ->latest()
     ->paginate(15);
```

Benefits:

-   Fewer database queries
-   Faster API responses
-   Reduced database load

------------------------------------------------------------------------

## 2. Large Response Payload

Some endpoints returned large datasets without pagination.

### Solution

Pagination introduced:

``` php
paginate(15)
```

Endpoints:

-   GET /api/products
-   GET /api/orders
-   GET /api/sales-report

Benefits:

-   Reduced payload size
-   Improved performance
-   Better frontend handling

------------------------------------------------------------------------

## 3. Database Indexing

Indexes added to frequently queried columns.

### Products

-   name
-   sold_count
-   category_id

### Orders

-   status
-   customer_id
-   created_at

### Order Items

-   order_id
-   product_id

Example migration:

``` php
$table->index('name');
$table->index('sold_count');
$table->index('category_id');
```

Benefits:

-   Faster filtering
-   Faster joins
-   Improved sorting

------------------------------------------------------------------------

## 4. Redis Caching

Frequently requested endpoints cached using Redis.

Cached endpoints:

-   GET /api/products
-   GET /api/products/dashboard

Example:

``` php
Cache::remember("products_page_{$page}", 300, function () {
    return Product::with('category')
        ->select('id','name','price','stock','category_id')
        ->paginate(15);
});
```

Cache TTL: **5 minutes**

Benefits:

-   Reduced database load
-   Faster repeated requests
-   Improved scalability

------------------------------------------------------------------------

## 5. Query Optimization

Sales report optimized.

Before:

-   Nested loops
-   Multiple DB queries

After:

``` php
OrderItem::with(['product:id,name','order.customer:id,name'])
    ->select('id','order_id','product_id','quantity','unit_price')
    ->paginate(15);
```

Benefits:

-   Eliminates N+1 queries
-   Reduces database calls
-   Faster responses

------------------------------------------------------------------------

## 6. Safe Search Implementation

``` php
Product::query()
    ->when($keyword, function ($query) use ($keyword) {
        $query->where(function ($q) use ($keyword) {
            $q->where('name','LIKE',"%{$keyword}%")
              ->orWhere('description','LIKE',"%{$keyword}%");
        });
    })
    ->paginate(15);
```

Benefits:

-   Safe filtering
-   Clean query logic

------------------------------------------------------------------------

# Cache Invalidation

Cache cleared when products/orders change.

Example:

``` php
Cache::forget('products_dashboard');
Cache::forget("products_page_{$page}");
```

------------------------------------------------------------------------

# Before vs After Improvements

  Optimization      Before             After
  ----------------- ------------------ ----------------------------
  Product queries   N+1 queries        Eager loading
  Orders list       Multiple queries   Optimized relation loading
  Sales report      Nested queries     Optimized query
  Response size     Large payload      Paginated
  Dashboard         DB every request   Redis cache
  Search            Full scan          Indexed columns

------------------------------------------------------------------------

# API Endpoints

  Endpoint                         Description
  -------------------------------- ----------------------
  GET /api/products                Product list
  GET /api/products/dashboard      Dashboard statistics
  GET /api/orders                  Order list
  POST /api/orders                 Create order
  GET /api/products/search         Product search
  GET /api/products/sales-report   Sales report

------------------------------------------------------------------------

# Optimization Architecture

``` text
Client Request
      |
      v
Laravel Controller
      |
      v
Redis Cache Check
      |
  Hit ---- Miss
   |         |
   |         v
   |    Query Database
   |         |
   |         v
   |   Store in Redis
   |         |
   v         v
Return Response
```

# Ripo Structure 

sherazi-pos-performance-audit
│
├── app/
├── database/
│   └── migrations/
│       └── add_performance_indexes.php
│
├── routes/
├── README.md
├── composer.json
└── ...

------------------------------------------------------------------------

# Technologies

-   Laravel
-   MySQL
-   Redis
-   Eloquent ORM

------------------------------------------------------------------------

# Conclusion

The system now uses optimized queries, Redis caching, database indexing,
and pagination to ensure better performance and scalability.
