<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Task and meeting file uploads
    |--------------------------------------------------------------------------
    |
    | Files attached to a task, a task note or a meeting go to the same disk as
    | the document repository (config/documents.php) through the same presigned
    | pipeline, but with a much lower ceiling: an attachment here is a photo, a
    | marked-up PDF or a spreadsheet, not a drawing set. Anything genuinely
    | large belongs in the repository, and the task screen offers a button to
    | file it there.
    |
    */

    'max_upload_bytes' => (int) env('TASKS_MAX_UPLOAD', 100 * 1024 * 1024),

];
