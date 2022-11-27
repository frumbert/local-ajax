# Ajax Bucket

Got some routines you want to run through ajax but don't know where to put them? Me too.

## Context / Logon

This requires logon and expects three parameters at least:

* action - to switch the lookup
* id - the course id
* sesskey - which you get from php sesskey()

## Making this work

Call it somehow

```js
require(['jquery',function($) {
    $.post('/local/ajax.php', {
        'action':'activitycompletion',
        'id':courseId,
        'sesskey':sesskey
    }).done(function(result) {
        console.dir(result);
    });
}]);
```

## Licence 

GPL3