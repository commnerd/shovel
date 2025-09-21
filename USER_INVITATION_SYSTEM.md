# User Invitation System

## Overview
The User Invitation System allows Super Admins and Admins to invite new users to join the platform via email invitations. Invited users receive an email with a secure link to set their password and create their account.

## Features

### 🔐 **Role-Based Access Control**
- **Super Admins**: Can invite users to any organization or no organization
- **Admins**: Can invite users to their organization only, with domain restrictions
- **Regular Users**: Cannot send invitations

### 📧 **Email Domain Restrictions**
- **Super Admins**: No domain restrictions - can invite any email address
- **Admins**: Can only invite emails from their organization's domain (except default "None" organization)
- **Default Organization Admins**: No domain restrictions

### 🔗 **Secure Invitation Process**
- Unique 60-character tokens for each invitation
- 7-day expiration period
- Single-use invitations (cannot be reused after acceptance)
- Auto-approval for invited users (bypass normal approval process)

## Database Schema

### `user_invitations` Table
```sql
- id: Primary key
- email: Unique email address being invited
- token: Unique 60-character secure token
- organization_id: Target organization (nullable for platform invites)
- invited_by: User ID of the person who sent the invitation
- expires_at: Invitation expiration timestamp
- accepted_at: Timestamp when invitation was accepted (nullable)
- created_at/updated_at: Standard timestamps
```

## API Endpoints

### Admin Routes (Require Admin or Super Admin)
- `GET /admin/invitations` - List invitations
- `GET /admin/invitations/create` - Show invitation form
- `POST /admin/invitations` - Send new invitation
- `DELETE /admin/invitations/{invitation}` - Delete invitation
- `POST /admin/invitations/{invitation}/resend` - Resend invitation

### Public Routes (No authentication required)
- `GET /invitation/{token}` - Show set password form
- `POST /invitation/{token}` - Create account from invitation

## Frontend Components

### Admin Interface
- **`Admin/UserInvitations/Index.vue`**: List of sent invitations with status tracking
- **`Admin/UserInvitations/Create.vue`**: Form to send new invitations

### Public Interface
- **`Auth/SetPassword.vue`**: Password creation form for invited users

## Workflow

### 1. **Sending Invitations**
```
Admin/Super Admin → Fill invitation form → Email sent → Invitation stored in DB
```

### 2. **Accepting Invitations**
```
User clicks email link → Set password form → Account created → Invitation marked accepted
```

### 3. **User Assignment**
- **With Organization**: Assigned to specified organization's default group and User role
- **Without Organization**: Assigned to default "None" organization

## Validation Rules

### Invitation Creation
- Email must be valid format
- Email cannot belong to existing user
- Email cannot have pending invitation
- Organization must exist (if specified)
- Admins can only invite to their organization
- Admins must respect domain restrictions

### Password Setting
- Name is required (max 255 characters)
- Password must meet security requirements
- Password confirmation must match
- Token must be valid, not expired, and not already used

## Permissions Matrix

| Action | Super Admin | Admin | Regular User |
|--------|-------------|--------|--------------|
| View invitations | All invitations | Own org only | ❌ No access |
| Send invitations | Any org/no org | Own org only | ❌ No access |
| Domain restrictions | ❌ None | ✅ Own domain | ❌ N/A |
| Delete invitations | Any invitation | Own org only | ❌ No access |
| Resend invitations | Any invitation | Own org only | ❌ No access |

## Security Features

### 🛡️ **Token Security**
- 60-character random tokens
- Single-use only
- 7-day expiration
- Secure URL generation

### 🔒 **Domain Validation**
- Admins restricted to their organization's email domain
- Prevents unauthorized cross-organization invitations
- Super Admins bypass all restrictions

### ⏰ **Expiration Handling**
- Automatic expiration after 7 days
- Expired invitations cannot be used
- Clear status indicators in admin interface

## Email Template

The invitation email includes:
- Inviter's name and organization context
- Clear call-to-action button
- Expiration date
- Security disclaimer
- Professional branding

## Testing

Comprehensive test suite includes:
- **Unit Tests**: Model functionality, token generation, validation
- **Feature Tests**: API endpoints, permissions, workflows
- **Integration Tests**: Complete end-to-end invitation process
- **Domain Restriction Tests**: Admin permission boundaries

## Navigation

New "User Invitations" link added to admin sidebar navigation for easy access by authorized users.

## Error Handling

- Graceful handling of expired/invalid tokens
- Clear validation messages
- Proper HTTP status codes
- User-friendly error messages
- Database transaction safety
