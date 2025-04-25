document.addEventListener('DOMContentLoaded', function () {
  const consolidateButton = document.createElement('button');
  consolidateButton.textContent = 'Consolidate';
  consolidateButton.id = 'consolidate-button';
  consolidateButton.classList.add('button', 'button-primary');
  const orderDiv = document.getElementById('order-list'); // Assuming the order list is in a div with id 'order-list'
  if (orderDiv) {
    orderDiv.parentNode.insertBefore(consolidateButton, orderDiv);
  }

  consolidateButton.addEventListener('click', function () {
    const rows = orderDiv.querySelectorAll('tr');
    const consolidated = {};
    const headerRow = rows[0];
    const newRows = [headerRow.cloneNode(true)];

    for (let i = 1; i < rows.length; i++) {
      const cells = rows[i].querySelectorAll('td');
      if (cells.length === 0) continue; // skip if no cells
      const ean = cells[2].textContent.trim(); // Assuming EAN is in the 3rd column (index 2)
      const quantity = parseInt(cells[3].textContent.trim(), 10); // Assuming Quantity is in the 4th column (index 3)
      if (!consolidated[ean]) {
        consolidated[ean] = {
          row: rows[i].cloneNode(true),
          quantity: quantity
        };
      } else {
        consolidated[ean].quantity += quantity;
      }
    }

    // Update quantities in consolidated rows
    Object.values(consolidated).forEach(item => {
      const qtyCell = item.row.querySelectorAll('td')[3];
      qtyCell.textContent = item.quantity;
      newRows.push(item.row);
    });

    // Clear existing rows and append consolidated rows
    while (orderDiv.firstChild) {
      orderDiv.removeChild(orderDiv.firstChild);
    }
    newRows.forEach(row => orderDiv.appendChild(row));
  });
});