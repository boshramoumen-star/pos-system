<?php
session_start();
$conn = new mysqli("localhost", "root", "", "pos_project");

//login
if (isset($_POST['login'])) {
    $username = $_POST['users'];
    $password = $_POST['pass'];
    $stmt = $conn->prepare("SELECT * FROM User WHERE username = ? AND password = ?");
    $stmt->bind_param("ss", $username, $password);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $_SESSION['logged_in'] = true;
        $_SESSION['username'] = $username;
        $_SESSION['cart'] = [];
    }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit(); }

// add product
if (isset($_POST['add_product'])) {
    $name = $_POST['p_name']; 
    $price = $_POST['p_price'];
     $stock = $_POST['p_stock'];
    $conn->query("INSERT INTO Product (productName, price, stock) VALUES ('$name', $price, $stock)");
}

//add to cart
if (isset($_POST['add_to_cart'])) {
    $p_id = $_POST['product_id'];
    $qty = $_POST['qty'];
    $res = $conn->query("SELECT * FROM Product WHERE productID = $p_id");
    $product = $res->fetch_assoc();
    if ($product['stock'] >= $qty) {
        $_SESSION['cart'][] = ['id' => $p_id, 'name' => $product['productName'], 'price' => $product['price'], 'qty' => $qty, 'subtotal' => $product['price'] * $qty];
    }
}

// checkout
if (isset($_POST['checkout']) && !empty($_SESSION['cart'])) {
    $total = 0;
    foreach ($_SESSION['cart'] as $item) { $total += $item['subtotal']; }
    $conn->query("INSERT INTO Sale (saleDate, totalAmount) VALUES (NOW(), $total)");
    $sale_id = $conn->insert_id;
    foreach ($_SESSION['cart'] as $item) {
        $conn->query("UPDATE Product SET stock = stock - {$item['qty']} WHERE productID = {$item['id']}");
        $conn->query("INSERT INTO SaleItem (saleID, productID, quantity, subtotal) VALUES ($sale_id, {$item['id']}, {$item['qty']}, {$item['subtotal']})");
    }
    $_SESSION['cart'] = [];
    echo "<script>alert('تم البيع بنجاح! فاتورة #$sale_id');</script>";
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>POS system</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma; margin: 0; display: flex; background: #f4f7f6; }
        .sidebar { width: 240px; background: #2c3e50; color: white; height: 100vh; position: fixed; padding: 20px 0; }
        .sidebar h3 { text-align: center; margin-bottom: 30px; }
        .sidebar-item { padding: 15px 25px; cursor: pointer; border-bottom: 1px solid #34495e; transition: 0.3s; }
        .sidebar-item:hover { background: #34495e; }
        .active { background: #1abc9c !important; }
        .main { margin-right: 240px; padding: 30px; width: 100%; }
        .content-section { display: none; }
        .card { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: center; }
        th { background: #ecf0f1; }
        input, select { padding: 10px; margin: 5px 0; border: 1px solid #ccc; border-radius: 4px; width: 100%; box-sizing: border-box; }
        .btn { padding: 12px 20px; border: none; cursor: pointer; color: white; border-radius: 5px; width: 8ح0%; font-size: 16px; }
        .btn-blue { background: #3498db; }
        .btn-green { background: #2ecc71; }
        .btn-red { background: #e74c3c; margin-top: 20px; }
    </style>
</head>
<body>

<?php if (!isset($_SESSION['logged_in'])): ?>
    <div style="width: 100%; text-align: center; margin-top: 100px;">
        <div class="card" style="display:inline-block; width: 350px;">
            <h2>تسجيل دخول النظام</h2>
            <form method="POST">
                <input type="text" name="users" placeholder="اسم المستخدم" required>
                <input type="password" name="pass" placeholder="كلمة المرور" required>
                <button type="submit" name="login" class="btn btn-blue">دخول</button>
            </form>
        </div>
    </div>
<?php else: ?>

    <div class="sidebar">
        <h3>نظام الإدارة</h3>
        <div class="sidebar-item active" onclick="showSection('pos-section', this)"> نقطة البيع</div>
        <div class="sidebar-item" onclick="showSection('inventory-section', this)"> إدارة المخزن</div>
        <div class="sidebar-item" onclick="showSection('reports-section', this)"> تقارير المبيعات</div>
        <div style="padding: 20px;">
            <a href="?logout=1" class="btn btn-red" style="text-decoration:none; display:block; text-align:center;">خروج</a>
        </div>
    </div>

    <div class="main">
        <div id="pos-section" class="content-section" style="display: block;">
            <h1>نقطة البيع (POS)</h1>
            <div style="display: flex; gap: 20px;">
                <div class="card" style="flex: 1;">
                    <h3>إضافة منتج للسلة</h3>
                    <form method="POST">
                        <select name="product_id" required>
                            <option value="">اختر المنتج...</option>
                            <?php 
                            $prods = $conn->query("SELECT * FROM Product WHERE stock > 0");
                            while($p = $prods->fetch_assoc()) echo "<option value='{$p['productID']}'>{$p['productName']} - {$p['price']}$</option>";
                            ?>
                        </select>
                        <input type="number" name="qty" value="1" min="1">
                        <button type="submit" name="add_to_cart" class="btn btn-blue">أضف للسلة</button>
                    </form>
                </div>
                <div class="card" style="flex: 2;">
                    <h3>سلة المشتريات</h3>
                    <table>
                        <tr><th>المنتج</th><th>السعر</th><th>الكمية</th><th>المجموع</th></tr>
                        <?php $total = 0; foreach($_SESSION['cart'] as $i): $total += $i['subtotal'];?>
                        <tr><td><?=$i['name']?></td><td><?=$i['price']?>$</td><td><?=$i['qty']?></td><td><?=$i['subtotal']?>$</td></tr>
                        <?php endforeach; ?>
                        <tr style="background:#f9f9f9"><b><td colspan="3">الإجمالي</td><td><?=$total?>$</td></b></tr>
                    </table>
                    <form method="POST"><button type="submit" name="checkout" class="btn btn-green" style="margin-top:15px">إتمام عملية البيع</button></form>
                </div>
            </div>
        </div>

        <div id="inventory-section" class="content-section">
            <h1>إدارة المخزون (Inventory)</h1>
            <div class="card">
                <h3>إضافة منتج جديد</h3>
                <form method="POST" style="display: flex; gap: 10px;">
                    <input type="text" name="p_name" placeholder="الاسم" required>
                    <input type="number" step="0.01" name="p_price" placeholder="السعر" required>
                    <input type="number" name="p_stock" placeholder="الكمية" required>
                    <button type="submit" name="add_product" class="btn btn-blue" style="width:200px">حفظ</button>
                </form>
            </div>
            <div class="card">
                <h3>قائمة المنتجات</h3>
                <table>
                    <tr><th>ID</th><th>اسم المنتج</th><th>السعر</th><th>الكمية المتوفرة</th></tr>
                    <?php $all_p = $conn->query("SELECT * FROM Product"); while($r = $all_p->fetch_assoc()): ?>
                    <tr><td><?=$r['productID']?></td><td><?=$r['productName']?></td><td><?=$r['price']?>$</td><td><?=$r['stock']?></td></tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>

        <div id="reports-section" class="content-section">
            <h1>تقارير المبيعات (Sales Reports)</h1>
            <div class="card">
                <table>
                    <tr><th>رقم الفاتورة</th><th>التاريخ</th><th>المبلغ الإجمالي</th></tr>
                    <?php $sales = $conn->query("SELECT * FROM Sale ORDER BY saleID DESC"); while($s = $sales->fetch_assoc()): ?>
                    <tr><td>#<?=$s['saleID']?></td><td><?=$s['saleDate']?></td><td><?=$s['totalAmount']?>$</td></tr>
                    <?php endwhile; ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        function showSection(sectionId, element) {
         
            document.querySelectorAll('.content-section').forEach(section => section.style.display = 'none');
           
            document.getElementById(sectionId).style.display = 'block';
         
            document.querySelectorAll('.sidebar-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');
        }
    </script>
<?php endif; ?>

</body>
</html>