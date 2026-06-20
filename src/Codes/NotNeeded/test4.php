
<style>
    canvas{
        margin-bottom: 100px;
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js" integrity="sha512-aVKKRRi/Q/YV+4mjoKBsE4x3H+BkegoM/em46NNlCqNTmUYADjBbeNefNxYV7giUp0VxICtqdrbqU7iVaeZNXA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
<script type="text/javascript" src="jcode/jquery-qrcode-master/jquery.qrcode.min.js"></script>
<div id="qrcode"></div>
<div id="tab">
            <table class="table" id="codes"></table>

        </div>
<script>
    $( document ).ready(function() {
        // $('#qrcode').qrcode("this plugin is great");
        // $('#qrcode').qrcode({width: 64,height: 64,text: "size doesn't matter"});
        var out = '<div id="tab"><table class="table" id="codes"><thead><tr><th>name</th></tr></thead><tbody>';
        var data = [
  { "id":1,"username": "John", "year": 1999},
  { "id":2,"username": "aya", "year": 1999},
  { "id":3,"username": "jojo", "year": 1999},
  {"id":4,"username": "kiko", "year": 1999},


]
        $.each(data, function(key, value) {
                          
                            out += '<tr ><td id="td'+value['id']+'"></td></tr>';

                        });
                        out += '</table ></div>';
                        $("#codes").html(out);
                        $.each(data, function(key, value) {
                            $("#td"+value['id']+"").qrcode({width: 64,height: 64,text: value['username'] })

                        });
});
</script>


