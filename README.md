# Sherazi POS Performance Audit

This project contains a performance audit and optimization of the
Sherazi POS API built with Laravel. The goal was to identify performance
bottlenecks and improve the system's scalability and response
efficiency.

------------------------------------------------------------------------

# Project Overview

The API manages:

-   Products
-   Orders
-   Categories
-   Sales Reports
-   Dashboard Statistics

The system originally had several performance issues such as N+1
queries, inefficient data retrieval, and lack of caching.

This audit focuses on identifying those problems and implementing
scalable solutions.

------------------------------------------------------------------------

# Performance Issues Identified

## 1. N+1 Query Problem

Several endpoints were loading related data inside loops, which caused
multiple unnecessary database queries.

Example: - Sales report - Orders listing - Product category loading

This resulted in significant query overhead.

### Solution

Eager loading was introduced using:

    with()
    withCount()

Example:

``` php
Order::with('customer')
     ->withCount('items')
     ->paginate(15);
```

This reduces database calls and improves query efficiency.

------------------------------------------------------------------------

# 2. Large Response Payload

Some endpoints returned large datasets without pagination.

### Solution

Pagination was implemented using:

``` php
paginate(15)
```

Endpoints updated:

-   /api/products
-   /api/orders
-   /api/sales-report

Benefits:

-   Reduced payload size
-   Improved API performance
-   Better client-side handling

------------------------------------------------------------------------

# 3. Database Indexing

Frequently queried columns were missing database indexes.

### Indexes Added

Products table:

-   name
-   sold_count
-   category_id

Orders table:

-   status
-   customer_id
-   created_at

Order Items table:

-   order_id
-   product_id

Example migration:

``` php
$table->index('name');
$table->index('sold_count');
$table->index('category_id');
```

These indexes improve query performance for:

-   search
-   sorting
-   filtering
-   joins

------------------------------------------------------------------------

# 4. Redis Caching

Certain endpoints repeatedly compute the same data.

Examples:

-   Product list
-   Dashboard statistics

To reduce database load, Redis caching was implemented.

### Cached Endpoints

GET /api/products\
GET /api/products/dashboard

### Implementation Example

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
-   Faster repeated responses
-   Better scalability

------------------------------------------------------------------------

# 5. Query Optimization

### Sales Report Optimization

``` php
OrderItem::with(['product:id,name','order.customer:id,name'])
    ->select('id','order_id','product_id','quantity','unit_price')
    ->paginate(15);
```

Benefits:

-   Eliminates N+1 queries
-   Reduces database calls
-   Improves response time

------------------------------------------------------------------------

# 6. Safe Search Implementation

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

-   Safe dynamic queries
-   Improved readability
-   Controlled filtering

------------------------------------------------------------------------

# Cache Invalidation Strategy

Whenever new data is created, relevant cache entries are cleared.

Example:

``` php
Cache::forget('products_dashboard');
```

------------------------------------------------------------------------

# Technologies Used

-   Laravel
-   MySQL
-   Redis
-   Eloquent ORM
-   API Resources

------------------------------------------------------------------------

# Key Improvements Summary

  Optimization         Impact
  -------------------- ------------------------------
  Eager Loading        Removed N+1 queries
  Pagination           Reduced payload size
  Database Indexing    Faster filtering and joins
  Redis Caching        Reduced database load
  Query Optimization   Improved response efficiency

------------------------------------------------------------------------

# Conclusion

The API was optimized by applying best practices in database indexing,
query optimization, caching, and pagination.

These improvements make the system more scalable, efficient, and
production-ready.
