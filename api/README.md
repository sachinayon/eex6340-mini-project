# API Endpoints for Chatbot Integration

This directory will contain API endpoints that your chatbot can use to query the e-commerce database.

## Suggested API Structure

### Order Status API
```
GET /api/order_status.php?order_number=ORD-20241115-XXXXX
```
Returns order status, items, and shipping information.

### Product Search API
```
GET /api/products.php?search=laptop&category=1
```
Returns product information for recommendations.

### User Orders API
```
GET /api/user_orders.php?user_id=1
```
Returns all orders for a specific user.

### Return Policy API
```
GET /api/return_policy.php
```
Returns return policy information in structured format.

### FAQ API
```
GET /api/faq.php
```
Returns FAQ data in JSON format.

## Implementation Notes

When implementing the chatbot, you can:
1. Create these API endpoints that return JSON data
2. Use the chatbot to query these endpoints
3. Format the responses for natural language output

## Example Response Format

```json
{
  "status": "success",
  "data": {
    "order_number": "ORD-20241115-XXXXX",
    "status": "shipped",
    "items": [...],
    "total": 899.99
  }
}
```

