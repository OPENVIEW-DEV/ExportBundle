/*
 * Jobs manager script
 */

var exporting = false;          // if TRUE, i'm exporting. Need to find when an export job has finished
var UPDATE_INTERVAL = 2500;     // interval between "pings"


/**
 * Update panel with current export job status
 * @param {object} job
 */
function updateStatus(job) {
    // update progress bar
    var percent = getPercentCompleted(job.exportedItems, job.totItems) + '%';
    $('.box-process-status .progress .progress-bar').css('width', percent);
    // update job values
    $('.property-status').text(job.statusDescription);
    $('.property-exportedItems').text(job.exportedItems);
    $('.property-totItems').text(job.totItems);
}


/**
 * Retrieves info about current job
 */
function checkStatus() {
    var checkUrl = Routing.generate("openview_export_api_v1_check");
    $.ajax({
        'url': checkUrl
    }).done(function(data){
        var jobStatus = JSON.parse(data);
        // if no job is active
        if (jobStatus.activejob == 0) {
            // sho start button
            $('.box-start-process').removeClass('hidden');
            $('.box-process-status').addClass('hidden');
            // if no job is active, but at previous check there was some job active, it means that it has just finished.
            // so show the finished export panel
            if (exporting) {
                exporting = false;
                $('.box-process-finished').removeClass('hidden');
            }
        } 
        // if a job is active
        else {
            // remember a job is currently in progress
            exporting = true;
            // show statusbar and hides everithing else
            $('.box-start-process').addClass('hidden');
            $('.box-process-status').removeClass('hidden');
            updateStatus(jobStatus.job);
        }
        $('.box-waiting').addClass('hidden');
    }).fail(function(){
        $('.box-waiting').addClass('hidden');
    });
}




/**
 * Return the % of exported records
 * @param {int} exported
 * @param {int} total
 * @returns {int}
 */
function getPercentCompleted(exported, total) {
    return Math.floor((exported / total) * 100);
}



$(document).ready(function() {
    checkStatus();
    
    // clic on start button
    $('#btn-start-export').click(function(){
        var startUrl = Routing.generate("openview_export_api_v1_start");
        $.ajax({
            'url': startUrl
        }).done(function(data){
            // if the job has been launched, update the interface
            checkStatus();
        });
    });
    
    // periodically update job status
    window.setInterval(function(){ 
        checkStatus();
    }, UPDATE_INTERVAL);
});
