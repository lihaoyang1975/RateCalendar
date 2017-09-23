
$('#btnLoad').click(loadSample);
$('#btnInsert').click(insertNew);
$('#btnSubmit').click(submitData);

// A uniqueId will be assigned to each data in the data set, so that we can identify it when deleting it.
var uniqueId = 0;
var actionDelete = '<button class="btn btn-primary" onclick="deleteData(uniqueId)">Delete</button>';

function loadSample() {
    $.getJSON("http://localhost:8000/Samples/SampleInput1.json", function (data) {
        for(var i=0; i<data.length; i++) {
            uniqueId++;
            data[i].id = uniqueId;
            data[i].actions = actionDelete.replace('uniqueId', uniqueId);
        }
        $('#divAlert').hide();
        $('#tblRateCalendar').bootstrapTable("load", data);
    });
}

function insertNew() {
    uniqueId++;
    var emptyRow = [{"periodStart":"", "periodEnd":"","rate":"","id":uniqueId,"actions":actionDelete.replace('uniqueId', uniqueId)}];
    $('#tblRateCalendar').bootstrapTable('prepend', emptyRow);
}

function deleteData(uniqueId) {
    $('#tblRateCalendar').bootstrapTable('remove', {field: 'id', values: [uniqueId]});
}

function submitData() {
    // export the table data to JSON
    var tblData = [];
    var colsToIgnore = ['colorCode'];

    // turn off pagination, otherwise only rows visible will be found
    $('#tblRateCalendar').bootstrapTable('togglePagination');

    $('#tblRateCalendar').find('tbody').find('tr').each(function() {
        var rateData = {};

        $(this).find('a[data-name]').each(function() {
            if (colsToIgnore.indexOf($(this).data('name')) == -1) {
                rateData[$(this).data('name')] = $(this).text().replace('Empty', '');
            }
        });

        if (!$.isEmptyObject(rateData)) tblData.push(rateData);
    });

    if (tblData.length == 0) {
        // Turn pagination back on
        $('#tblRateCalendar').bootstrapTable('togglePagination');
        alert("There is no data available to be submitted.");
        return;
    }

    $.ajax({
        type: 'POST',
        url: 'http://localhost:8000/index.php',
        contentType: 'application/json',
        data: JSON.stringify(tblData),
        async: true,
        success: function (resp) {
                    if (resp.errors) {
                        $('#ulErrors').empty();
                        for(var i=0; i<resp.errors.length; i++) {
                            var error = resp.errors[i];
                            $('#ulErrors').append('<li>' + error.msg + '<br>' + error.data + '</li>');
                        }
                        $('#divAlert').show();
                    } else if (resp.data) {
                        $('#divAlert').hide();
                        for(var i=0; i<resp.data.length; i++) {
                            uniqueId++;
                            resp.data[i].colorCode = '<span class="color-box" style="background-color:#' + resp.data[i].colorCode + '"></span>';
                            resp.data[i].id = uniqueId;
                            resp.data[i].actions = actionDelete.replace('uniqueId', uniqueId);
                        }
                        // Load the table with the new data
                        $('#tblRateCalendar').bootstrapTable('load', resp.data);
                    }
                },
        dataType: 'json'
    });

    // Turn pagination back on
    $('#tblRateCalendar').bootstrapTable('togglePagination');
}
