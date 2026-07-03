# Feature manquant — Module WHMCS SmarterMail

## Gestion de l'auto responder.

- https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SettingsController/GetAutoResponderSettings/get
- https://mail.smartertools.com/Documentation/api#/reference/SmarterMail.Web.Api.SettingsController/SetAutoResponderSettings/post

### SetAutoResponderSettings

Sets the autoresponder configuration for the current user. Allows configuration of message content, date ranges, and exclusion rules.

An authentication token is REQUIRED for this call. This call is limited to Users.

The following response codes are possible with this call:
200 - OK
400 - Bad Request (Additional details may appear in the response)
401 - Unauthorized, Token Invalid, or Token Expired

Calling the Function
POST api/v1/settings/auto-responder
Post Inputs

```json
{ 
"autoResponderSettings": 
{ 
"endDateUtc": date,
"startDateUtc": date,
"useActiveDateRange": boolean,
"body": text,
"externalReply": text,
"enabled": boolean,
"isHTML": boolean,
"subject": text,
"autoRespondOnDirectMailOnly": boolean,
"externalAudience": number
}
}
```

Returns

```json
{ 
"success": boolean,// True if the function call succeeded.
"message": text,// If success is false, this field tells you why the call failed.
"variables": // If success is false, this contains any variables that might be needed to fully translate the message.
// This element is a dictionary.
"key": 
{ 
},
...
}
```

### GetAutoResponderSettings

Gets the autoresponder configuration for the current user. Returns the autoresponder message, schedule, and enabled status. The optional wantHtml parameter returns HTML-formatted message content.

An authentication token is REQUIRED for this call. This call is limited to Users.

The following response codes are possible with this call:
200 - OK
400 - Bad Request (Additional details may appear in the response)
401 - Unauthorized, Token Invalid, or Token Expired

Calling the Function
GET api/v1/settings/auto-responder/{wantHtml?}
wantHtml	boolean	

Returns

```json
{ 
"autoResponderSettings": // The autoresponder configuration settings for the user.
{ 
"endDateUtc": date,
"startDateUtc": date,
"useActiveDateRange": boolean,
"body": text,
"externalReply": text,
"enabled": boolean,
"isHTML": boolean,
"subject": text,
"autoRespondOnDirectMailOnly": boolean,
"externalAudience": number
},
"success": boolean,// True if the function call succeeded.
"message": text,// If success is false, this field tells you why the call failed.
"variables": // If success is false, this contains any variables that might be needed to fully translate the message.
// This element is a dictionary.
"key": 
{ 
},
...
}
```
