
<?php
$user = 'root';
$pass = '';
//Maak connectie met de oude en de nieuwe database
$db = new PDO('mysql:host=localhost;dbname=old', $user, $pass);
$new = new PDO('mysql:host=localhost;dbname=new', $user, $pass);

//ga voor  elke user in de oude database na welke role ze hebben en groepeer deze rollen
foreach($db->query('SELECT role from users GROUP BY role') as $row) {
    $role = $row[0];

    //voeg de rollen aan de nieuwe database in de nieuwe tabel roles
    $insert_role = $new->prepare('INSERT INTO roles(name) VALUES (:role)');
    $insert_role->bindParam(':role', $role);
    $insert_role->execute();

}
//haal alles op uit de oude database uit de tabel users
foreach($db->query('SELECT * from users') as $row) {
    $street = $row[4];
    $housenumber = $row[5];
    $postcode = $row[6];
    //voeg voor alle oude gebruikers in de nieuwe database het adres toe in de nieuwe tabel addresses
    $insert_adres = $new->prepare('INSERT INTO addresses(street, house_number, postcode) VALUES (:street, :housenumber, :postcode)');
    $insert_adres->bindParam(':street', $street);
    $insert_adres->bindParam(':housenumber', $housenumber);
    $insert_adres->bindParam(':postcode', $postcode);
    $insert_adres->execute();
    //haal het laatst ingevoerde id op dat is toegevoegd aan de nieuwe database (in dit geval is dat het adres_id
    $address_id = $new->lastInsertId();

    //haal de naam in de oude database en splits dit in voornaam en achternaam (en tussenvoegsel)
    $name = explode(' ', $row[3]);
    $first_name = $name[0];
    $last_name = $name[1];

    //voeg het profiel van oude gebruikers toe aan de nieuwe database met daarin de gesplitste naam
    $insert_profile = $new->prepare('INSERT INTO profiles(first_name, last_name) VALUES (:first_name, :last_name)');
    $insert_profile->bindParam(':first_name', $first_name);
    $insert_profile->bindParam(':last_name', $last_name);
    $insert_profile->execute();

    //haal het laatst ingevoerde id op dat is toegevoegd aan de nieuwe database (in dit geval is dat het profile_id
    $profile_id = $new->lastInsertId();

    //haal de rollen op die in de nieuwe tabel zijn toegevoegd
    $get_role_id = $new->prepare('SELECT id FROM roles WHERE name = :role');
    $get_role_id->bindParam(':role', $row['role']);
    $get_role_id->execute();

    $role_id = $get_role_id->fetch(PDO::FETCH_ASSOC);

    //gebruik de ids van adres, profile en role om in de nieuwe tabel de nieuwe gebruikers toe te voegen.
    $insert_user = $new->prepare(" INSERT INTO users(email, password, Address_id, Profile_id, Role_id)
                                        VALUES (:email,
                                           :password,
                                            :address_id,
                                            :profile_id,
                                            :role_id)");
    $insert_user->bindParam(':email', $row['email']);
    $insert_user->bindParam(':password', $row['password']);
    $insert_user->bindParam(':address_id', $address_id);
    $insert_user->bindParam(':profile_id', $profile_id );
    $insert_user->bindParam(':role_id', $role_id['id'] );

    $insert_user->execute();
    //haal het laatst ingevoerde id op dat is toegevoegd aan de nieuwe database (in dit geval is dat het user_id)
    $user_id = $new->LastInsertID();

    //haal de files op uit de oude database met daarbij wie die files heeft geupload
    $get_filename = $db->prepare("SELECT filename FROM file WHERE uploaded_by = :uploaded_by");
    $get_filename->bindParam(':uploaded_by', $row['name']);
    $get_filename->execute();
    $filename = $get_filename->fetch(PDO::FETCH_ASSOC);

    //Als de filename false is, heeft de huidige user geen bestanden geupload, dus word er niets ge-insert.
    if ($filename == false) {
    }
    else{
        //de nieuwe files worden ingevoerd in de nieuwe database
        $insert_file = $new->prepare(" INSERT INTO files(User_id, filename)
                                        VALUES (:user_id,
                                            :filename)");
        $insert_file->bindParam(':user_id', $user_id );
        $insert_file->bindParam(':filename', $filename['filename']);
        $insert_file->execute();
    }
}
//haal alle blogs op uit de oude database
$get_blogs = $db->prepare("SELECT * FROM blog");
$get_blogs->execute();
$blogs = $get_blogs->fetchAll(PDO::FETCH_ASSOC);

//haal alle comments op uit de oude database
$get_comments = $db->prepare("SELECT * FROM comment");
$get_comments->execute();
$comments = $get_comments->fetchAll(PDO::FETCH_ASSOC);

//kijk voor elke blog wie het heeft geschreven en voeg het daarna toe aan de nieuwe database
foreach($blogs as $blog){

    //haal de gebruiker op die de blog heeft geplaatst
    $get_old_user_id = $db->prepare("SELECT Users_id
                                    FROM blog
                                    WHERE id = :blog_id
                                    ");
    $get_old_user_id->bindParam(':blog_id', $blog['id'] );
    $get_old_user_id->execute();
    $old_user_id = $get_old_user_id->fetch(PDO::FETCH_ASSOC);

    //haal de email op van de gebruiker die de blog heeft geplaatst
    $get_user_email = $db->prepare("SELECT email
                                    FROM users
                                    WHERE id = :user_id
                                    ");
    $get_user_email->bindParam(':user_id', $old_user_id['Users_id'] );
    $get_user_email->execute();
    $user_email = $get_user_email->fetch(PDO::FETCH_ASSOC);

    //haal de id op van de gebruiker die de blog heeft geplaatst
    $get_user_id = $new->prepare("SELECT id
                                    FROM users
                                    WHERE email = :user_email
                                    ");
    $get_user_id->bindParam(':user_email', $user_email['email'] );
    $get_user_id->execute();
    $user_id = $get_user_id->fetch(PDO::FETCH_ASSOC);

    //voeg met de opgehaalde gegevens de blogs toe aan de nieuwe database
    $insert_blog = $new->prepare(" INSERT INTO blogs(User_id, title, content)
                                        VALUES (:user_id,
                                            :title,
                                            :content)
                                            ");
    $insert_blog->bindParam(':user_id', $user_id['id'] );
    $insert_blog->bindParam(':title', $blog['title'] );
    $insert_blog->bindParam(':content', $blog['content'] );
    $insert_blog->execute();

//haal het laatst ingevoerde id op dat is toegevoegd aan de nieuwe database (in dit geval is dat het blog_id)
    $blog_id = $new->lastInsertId();

    // haal de comments op die bij de desbetreffende blog zijn geplaatst
    $get_comments = $db->prepare("SELECT * FROM comment WHERE Blog_id = :blog_id");
    $get_comments->bindParam(':blog_id', $blog['id'] );
    $get_comments->execute();
    $comments = $get_comments->fetchAll(PDO::FETCH_ASSOC);

    //kijk voor elke comment waar het bij hoort en voeg het toe aan de nieuwe database
    foreach($comments as $comment){

        // haal de auteur op van de comment uit de oude database
        $get_comment_author = $db->prepare("SELECT author
                                    FROM comment
                                    WHERE Blog_id = :blog_id
                                    ");
        $get_comment_author ->bindParam(':blog_id', $blog['id'] );
        $get_comment_author ->execute();
        $comment_author = $get_comment_author->fetch(PDO::FETCH_ASSOC);

        // haal de email op van de auteur van de comment uit de oude database
        $get_author_email = $db->prepare("SELECT email
                                    FROM users
                                    WHERE name = :author
                                    ");
        $get_author_email ->bindParam(':author', $comment_author['author']);
        $get_author_email ->execute();
        $author_email = $get_author_email->fetch(PDO::FETCH_ASSOC);

        // haal de id van de auteur op uit de nieuwe database
        $get_author_user_id = $new->prepare("SELECT id
                                    FROM users
                                    WHERE email = :email
                                    ");
        $get_author_user_id ->bindParam(':email', $author_email['email']);
        $get_author_user_id ->execute();
        $author_user_id = $get_author_user_id->fetch(PDO::FETCH_ASSOC);

        //voeg de comments met behulp van de opgehaalde gegevens toe aan de nieuwe database
        $insert_comments = $new->prepare(" INSERT INTO comments(Blog_id, User_id, text)
                                            VALUES(:blog_id,
                                            :user_id,
                                            :text)
                                            ");
        $insert_comments->bindParam(':blog_id', $blog_id );
        $insert_comments->bindParam(':user_id', $author_user_id['id'] );
        $insert_comments->bindParam(':text', $comment['text'] );
        $insert_comments->execute();

    }
}
?>
