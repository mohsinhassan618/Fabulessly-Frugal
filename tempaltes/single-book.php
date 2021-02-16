<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="">
  <meta name="author" content="">
  <title>Bare - Start Bootstrap Template</title>
  <!-- Bootstrap core CSS -->
    <?php wp_head(); ?>
</head>
<body>
<?php wp_body_open();
global $post;
$bookMeta = get_post_meta($post->ID,'book_details',true);
$bookISBN = get_post_meta($post->ID,'isbn',true);

$authors = '';
$publishers = '';
foreach($bookMeta['authors'] as $key => $value)
{
    if($key ==0){
        $authors .= $value;
    }else{
        $authors .= ','.$value;
    }
}
foreach($bookMeta['publishers'] as $key => $value)
{
    if($key==0){
        $publishers .=$value;
    }else{
        $publishers .= ','.$value;
    }
}
?>
<div class="container">
    <div class="row " >
        <p class="text-center">
            <h1>Following is the Book Information</h1>
        </p>
    </div>
</div>
<div class="container">
    <div class="row " style="">
        <table class="table">
            <thead>
            <tr>
                <th>ISBN</th>
                <th scope="col">Authors</th>
                <th scope="col">Publishers</th>
                <th scope="col">Publish Date</th>
                <th scope="col">Number Of Pages</th>
            </tr>
            </thead>
            <tbody>
            <tr>
                <td scope="row"><?php echo !empty($bookISBN) ? $bookISBN : 'N/A' ?></td>
                <th><?php echo !empty($authors)? $authors:'N/A'; ?></th>
                <td><?php echo !empty($publishers)? $publishers:'N/A'; ?></td>
                <td><?php echo !empty($bookMeta['publish_date'])? $bookMeta['publish_date']:'N/A'; ?></td>
                <td><?php echo !empty($bookMeta['number_of_pages'])? $bookMeta['number_of_pages']:'N/A'; ?></td>
            </tr>

            </tbody>
        </table>
    </div>
</div>
  <!-- Bootstrap core JavaScript -->
<?php wp_footer(); ?>
</body>
</html>