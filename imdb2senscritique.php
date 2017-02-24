<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 'on');

$totalCount = 0;
$filePath = 'movies.csv';

?>

<!DOCTYPE html>
<html>
    <head>
        <title>imdb2senscritique</title>
        <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">
        <style>span.alert{padding: 0 .25em;}</style>
        <script src="http://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js" type="text/javascript"></script>
        <script type="text/javascript">
            $(document).ready(function() {
                $('#import_form').on('click', 'input[type=button]', function(event) {
                    var fields = ['mail', 'start', 'number', 'pswd'];
                    var hasError = false;

                    for (var i = 0; i < fields.length; i++) {
                        var thisField = $('#'+fields[i]);
                        if (thisField.val().length === 0) {
                            thisField.parent().removeClass('has-success').addClass('has-error');
                            hasError = true;
                        } else {
                            thisField.parent().removeClass('has-error').addClass('has-success');
                        }
                    }

                    if (!hasError) {
                        var formData = new FormData($('#import_form')[0]);
                        formData.append('action', event.target.value);
                        $.ajax({
                            url: 'src/processFile.php',
                            type: 'POST',
                            // Ajax events
                            success: function(data) {
                                var thisData = jQuery.parseJSON(data);
                                $('#start').val(thisData.start);
                                $('#feedback').html(thisData.feedback);
                            },
                            error: function(data) {
                                if (data.status === 401) {
                                    $('#mail').parent().removeClass('has-success').addClass('has-error');
                                    $('#pswd').parent().removeClass('has-success').addClass('has-error');
                                } else {
                                    alert("Something went wrong!");
                                }
                            },
                            // Form data
                            data: formData,
                            // Options to tell jQuery not to process data or worry about the content-type
                            cache: false,
                            contentType: false,
                            processData: false
                        }, 'json');
                    }
                });
            });
        </script>
    </head>
    <body>
        <main class="container">
            <h1>imdb2senscritique</h1>
            <p>Updates <a href="https://www.senscritique.com">SensCritique</a> ratings from a IMDB export CSV file.</p>
            <hr/>
            <form id="import_form" method="post" class="form-horizontal" enctype="multipart/form-data">
                <div class="row form-group">
                    <div class="col-md-4 inputGroupContainer">
                        <label for="file">IMDB export file</label>
                        <div class="input-group">
                            <input type="file" name="file" id="file" />
                            <?php if(file_exists($filePath)):?>
                                <p class="help-block">Existing file uploaded on <?php echo date ("F d, Y", filemtime($filePath)); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 inputGroupContainer">
                        <label for="start">Start item</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="start" id="start" value="1">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="number">Item number</label>
                        <div class="input-group">
                            <input type="number" class="form-control" name="number" id="number" value="100">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-4">
                        <label for="mail">SC Email</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                            <input type="email" class="form-control" name="mail" id="mail" placeholder="email address" value="">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="pswd">SC Password</label>
                        <div class="input-group">
                            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                            <input type="password" class="form-control" name="pswd" id="pswd" placeholder="password" autocomplete="new-password" value="">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="over">Overwrite</label>
                        <div class="checkbox">
                            <label><input type="checkbox" name="over" > Overwrite existing ratings</label>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-md-6">
                        <input type="button" class="btn btn-primary btn-lg btn-block" value="Go">
                    </div>
                    <?php if(file_exists($filePath)):?>
                    <div class="col-md-6">
                        <input type="button" name="next" class="btn btn-primary btn-lg btn-block" value="Next">
                    </div>
                    <?php endif; ?>
                </div>
            </form>
            <hr/>
            <section id="feedback">
            </section>
        </main>
    </body>
</html>
