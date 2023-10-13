# Clockify notes

## API responses

### `/workspaces/{workspaceId}/user/{userId}/time-entries`

Without hydration:

```json
{
  "id": "63b03060b01ebfad292c61ed",
  "description": "Description",
  "tagIds": [],
  "userId": "b4de890ff78038db26859c0a",
  "billable": true,
  "taskId": "863f50306dd734f7f70a5327",
  "projectId": "97210bf336f73c996f45f6e1",
  "workspaceId": "79d95471c93cff26089752cc",
  "timeInterval": {
    "start": "2023-08-28T05:48:00Z",
    "end": "2023-08-28T08:42:00Z",
    "duration": "PT2H54M"
  },
  "customFieldValues": [],
  "type": "REGULAR",
  "kioskId": null,
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "costRate": {
    "amount": 0,
    "currency": "AUD"
  },
  "isLocked": false
}
```

With hydration:

```json
{
  "id": "63b03060b01ebfad292c61ed",
  "description": "Description",
  "tags": [],
  "user": {
    "id": "b4de890ff78038db26859c0a",
    "email": "email@domain.com",
    "name": "Luke",
    "memberships": [],
    "profilePicture": "https://img.clockify.me/profile.PNG",
    "activeWorkspace": "79d95471c93cff26089752cc",
    "defaultWorkspace": "79d95471c93cff26089752cc",
    "settings": {
      "weekStart": "MONDAY",
      "timeZone": "Australia/Sydney",
      "timeFormat": "HOUR24",
      "dateFormat": "DD/MM/YYYY",
      "sendNewsletter": false,
      "weeklyUpdates": true,
      "longRunning": true,
      "scheduledReports": true,
      "approval": true,
      "pto": true,
      "alerts": true,
      "reminders": true,
      "timeTrackingManual": false,
      "summaryReportSettings": {
        "group": "Project",
        "subgroup": "Time Entry"
      },
      "isCompactViewOn": true,
      "dashboardSelection": "ME",
      "dashboardViewType": "PROJECT",
      "dashboardPinToTop": false,
      "projectListCollapse": 50,
      "collapseAllProjectLists": false,
      "groupSimilarEntriesDisabled": true,
      "myStartOfDay": "09:00",
      "projectPickerTaskFilter": true,
      "lang": "EN",
      "multiFactorEnabled": false,
      "theme": "DEFAULT",
      "scheduling": true,
      "onboarding": true,
      "showOnlyWorkingDays": false
    },
    "status": "ACTIVE",
    "customFields": []
  },
  "userId": "b4de890ff78038db26859c0a",
  "billable": true,
  "task": {
    "id": "863f50306dd734f7f70a5327",
    "name": "Consulting",
    "projectId": "97210bf336f73c996f45f6e1",
    "assigneeIds": [],
    "assigneeId": null,
    "userGroupIds": [],
    "estimate": "PT0S",
    "status": "ACTIVE",
    "budgetEstimate": 0,
    "duration": "PT79H12M",
    "billable": true,
    "hourlyRate": {
      "amount": 4500,
      "currency": "AUD"
    },
    "costRate": null
  },
  "project": {
    "id": "97210bf336f73c996f45f6e1",
    "name": "Project",
    "hourlyRate": {
      "amount": 4500,
      "currency": "AUD"
    },
    "clientId": "4324630bfe76b597e688dbab",
    "workspaceId": "79d95471c93cff26089752cc",
    "billable": true,
    "memberships": [
      {
        "userId": "b4de890ff78038db26859c0a",
        "hourlyRate": null,
        "costRate": null,
        "targetId": "97210bf336f73c996f45f6e1",
        "membershipType": "PROJECT",
        "membershipStatus": "ACTIVE"
      }
    ],
    "color": "#9C27B0",
    "estimate": {
      "estimate": "PT0S",
      "type": "AUTO"
    },
    "archived": false,
    "duration": "PT0S",
    "clientName": "Client",
    "note": "",
    "costRate": null,
    "timeEstimate": {
      "estimate": "PT0S",
      "type": "AUTO",
      "resetOption": null,
      "active": false,
      "includeNonBillable": true
    },
    "budgetEstimate": null,
    "template": false,
    "public": true
  },
  "timeInterval": {
    "start": "2023-08-28T05:48:00Z",
    "end": "2023-08-28T08:42:00Z",
    "duration": "PT2H54M"
  },
  "workspaceId": "79d95471c93cff26089752cc",
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "customFieldValues": [],
  "type": "REGULAR",
  "kiosk": null,
  "costRate": {
    "amount": 0,
    "currency": "AUD"
  },
  "taskId": "863f50306dd734f7f70a5327",
  "tagIds": [],
  "projectId": "97210bf336f73c996f45f6e1",
  "kioskId": null,
  "isLocked": false
}
```

### `/workspaces/{workspaceId}/time-entries/{id}`

Without hydration:

```json
{
  "id": "63b03060b01ebfad292c61ed",
  "description": "Description",
  "tagIds": [],
  "userId": "b4de890ff78038db26859c0a",
  "billable": true,
  "taskId": "863f50306dd734f7f70a5327",
  "projectId": "97210bf336f73c996f45f6e1",
  "workspaceId": "79d95471c93cff26089752cc",
  "timeInterval": {
    "start": "2023-08-28T05:48:00Z",
    "end": "2023-08-28T08:42:00Z",
    "duration": "PT2H54M"
  },
  "customFieldValues": [],
  "type": "REGULAR",
  "kioskId": null,
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "costRate": {
    "amount": 0,
    "currency": "AUD"
  },
  "isLocked": false
}
```

With hydration:

```json
{
  "id": "63b03060b01ebfad292c61ed",
  "description": "Description",
  "tags": [],
  "user": null,
  "userId": "b4de890ff78038db26859c0a",
  "billable": true,
  "task": {
    "id": "863f50306dd734f7f70a5327",
    "name": "Consulting",
    "projectId": "97210bf336f73c996f45f6e1",
    "assigneeIds": [],
    "assigneeId": null,
    "userGroupIds": [],
    "estimate": "PT0S",
    "status": "ACTIVE",
    "budgetEstimate": 0,
    "duration": "PT79H12M",
    "billable": true,
    "hourlyRate": {
      "amount": 4500,
      "currency": "AUD"
    },
    "costRate": null
  },
  "project": {
    "id": "97210bf336f73c996f45f6e1",
    "name": "Project",
    "hourlyRate": {
      "amount": 4500,
      "currency": "AUD"
    },
    "clientId": "4324630bfe76b597e688dbab",
    "workspaceId": "79d95471c93cff26089752cc",
    "billable": true,
    "memberships": [
      {
        "userId": "b4de890ff78038db26859c0a",
        "hourlyRate": null,
        "costRate": null,
        "targetId": "97210bf336f73c996f45f6e1",
        "membershipType": "PROJECT",
        "membershipStatus": "ACTIVE"
      }
    ],
    "color": "#9C27B0",
    "estimate": {
      "estimate": "PT0S",
      "type": "AUTO"
    },
    "archived": false,
    "duration": "PT0S",
    "clientName": "Client",
    "note": "",
    "costRate": null,
    "timeEstimate": {
      "estimate": "PT0S",
      "type": "AUTO",
      "resetOption": null,
      "active": false,
      "includeNonBillable": true
    },
    "budgetEstimate": null,
    "template": false,
    "public": true
  },
  "timeInterval": {
    "start": "2023-08-28T05:48:00Z",
    "end": "2023-08-28T08:42:00Z",
    "duration": "PT2H54M"
  },
  "workspaceId": "79d95471c93cff26089752cc",
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "customFieldValues": [],
  "type": "REGULAR",
  "kiosk": null,
  "costRate": {
    "amount": 0,
    "currency": "AUD"
  },
  "projectId": "97210bf336f73c996f45f6e1",
  "taskId": "863f50306dd734f7f70a5327",
  "tagIds": [],
  "kioskId": null,
  "isLocked": false
}
```

### `/workspaces/{workspaceId}/reports/detailed` (`POST`)

`invoicingInfo` is absent or `[]` if an entry has not been invoiced, otherwise
it is:

```json
{
  "invoicingInfo": {
    "manuallyInvoiced": true
  }
}
```

```json
{
  "totals": [
    {
      "_id": "",
      "totalTime": 10440,
      "totalBillableTime": 10440,
      "entriesCount": 1,
      "totalAmount": 13050,
      "amounts": [
        {
          "type": "EARNED",
          "value": 13050
        }
      ]
    }
  ],
  "timeentries": [
    {
      "_id": "63b03060b01ebfad292c61ed",
      "description": "Description",
      "userId": "b4de890ff78038db26859c0a",
      "timeInterval": {
        "start": "2023-08-28T15:48:00+10:00",
        "end": "2023-08-28T18:42:00+10:00",
        "duration": 10440
      },
      "billable": true,
      "projectId": "97210bf336f73c996f45f6e1",
      "taskId": "863f50306dd734f7f70a5327",
      "tagIds": [],
      "approvalRequestId": null,
      "type": "REGULAR",
      "isLocked": false,
      "amount": 13050,
      "rate": 4500,
      "projectName": "Project",
      "projectColor": "#9C27B0",
      "clientName": "Client",
      "clientId": "4324630bfe76b597e688dbab",
      "taskName": "Consulting",
      "userName": "Luke",
      "userEmail": "email@domain.com"
    }
  ]
}
```

### /workspaces/{workspaceId}/projects

Without hydration:

```json
{
  "id": "97210bf336f73c996f45f6e1",
  "name": "Project",
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "clientId": "4324630bfe76b597e688dbab",
  "workspaceId": "79d95471c93cff26089752cc",
  "billable": true,
  "memberships": [
    {
      "userId": "b4de890ff78038db26859c0a",
      "hourlyRate": null,
      "costRate": null,
      "targetId": "97210bf336f73c996f45f6e1",
      "membershipType": "PROJECT",
      "membershipStatus": "ACTIVE"
    }
  ],
  "color": "#9C27B0",
  "estimate": {
    "estimate": "PT0S",
    "type": "AUTO"
  },
  "archived": false,
  "duration": "PT88H36M",
  "clientName": "Client",
  "note": "",
  "costRate": null,
  "timeEstimate": {
    "estimate": "PT0S",
    "type": "AUTO",
    "resetOption": null,
    "active": false,
    "includeNonBillable": true
  },
  "budgetEstimate": null,
  "template": false,
  "public": true
}
```

With hydration:

```json
{
  "id": "97210bf336f73c996f45f6e1",
  "name": "Project",
  "hourlyRate": {
    "amount": 4500,
    "currency": "AUD"
  },
  "clientId": "4324630bfe76b597e688dbab",
  "client": {
    "id": "4324630bfe76b597e688dbab",
    "name": "Client",
    "email": null,
    "workspaceId": "79d95471c93cff26089752cc",
    "archived": false,
    "address": "",
    "note": ""
  },
  "workspaceId": "79d95471c93cff26089752cc",
  "billable": true,
  "memberships": [
    {
      "userId": "b4de890ff78038db26859c0a",
      "hourlyRate": null,
      "costRate": null,
      "targetId": "97210bf336f73c996f45f6e1",
      "membershipType": "PROJECT",
      "membershipStatus": "ACTIVE"
    }
  ],
  "color": "#9C27B0",
  "estimate": {
    "estimate": "PT0S",
    "type": "AUTO"
  },
  "archived": false,
  "tasks": [
    {
      "id": "863f50306dd734f7f70a5327",
      "name": "Consulting",
      "projectId": "97210bf336f73c996f45f6e1",
      "assigneeIds": [],
      "assigneeId": null,
      "userGroupIds": [],
      "estimate": "PT0S",
      "status": "ACTIVE",
      "budgetEstimate": 0,
      "duration": "PT79H12M",
      "billable": true,
      "hourlyRate": {
        "amount": 4500,
        "currency": "AUD"
      },
      "costRate": null,
      "favorite": true
    },
    {
      "id": "59a57caed18e90ff298b5cfa",
      "name": "Priority consulting",
      "projectId": "97210bf336f73c996f45f6e1",
      "assigneeIds": [],
      "assigneeId": null,
      "userGroupIds": [],
      "estimate": "PT0S",
      "status": "ACTIVE",
      "budgetEstimate": 0,
      "duration": "PT8H36M",
      "billable": true,
      "hourlyRate": {
        "amount": 6000,
        "currency": "AUD"
      },
      "costRate": null,
      "favorite": true
    }
  ],
  "note": "",
  "duration": "PT88H36M",
  "costRate": null,
  "timeEstimate": {
    "estimate": "PT0S",
    "type": "AUTO",
    "resetOption": null,
    "active": false,
    "includeNonBillable": true
  },
  "budgetEstimate": null,
  "customFields": [],
  "currency": {
    "id": "c57e403badb621cd7a8ea22b",
    "code": "AUD"
  },
  "favorite": true,
  "template": false,
  "public": true
}
```
