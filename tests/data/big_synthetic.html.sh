#!/bin/bash

fn=big_synthetic.html
lines=${1:-19000}

cat > "$fn" <<EOS
<!DOCTYPE html>
<html>
<head>
</head>
<body>
EOS

i=0
while [[ $i -lt $lines ]]; do
    echo '    <div><div>19dnbfkjsb asdhfjkashjkfhalkshdfljkhaskdj fhkajsdfkjaslflkjashdlfkhaskldfhaklsj hdflkasdfkjlhasdflkashdklfj hasdk</div></div>' \
        >> "$fn"
    i=$(( i + 1 ))
done

cat >> "$fn" <<EOS
</body>
</script>
</html>
EOS

echo "done"
