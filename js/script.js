document.addEventListener('DOMContentLoaded', function() {
    // Handle Start Course button click
    const startCourseBtn = document.getElementById('startCourse');
    if (startCourseBtn) {
        startCourseBtn.addEventListener('click', function() {
            window.location.href = 'chapter1.html';
        });
    }

    // Handle View Contents button click
    const viewContentsBtn = document.getElementById('viewContents');
    if (viewContentsBtn) {
        viewContentsBtn.addEventListener('click', function() {
            document.getElementById('tableOfContents').scrollIntoView({ 
                behavior: 'smooth' 
            });
        });
    }

    // Handle PDF download
    const downloadPdfBtn = document.getElementById('downloadPdf');
    if (downloadPdfBtn) {
        downloadPdfBtn.addEventListener('click', function() {
            generatePDF();
        });
    }

    // Animation for graphic elements
    const graphicElements = document.querySelectorAll('.graphic-element');
    graphicElements.forEach(element => {
        element.addEventListener('mouseover', function() {
            this.style.transform = 'scale(1.1)';
        });
        
        element.addEventListener('mouseout', function() {
            this.style.transform = 'scale(1)';
        });
    });

    // Add smooth scrolling to all links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelector(this.getAttribute('href')).scrollIntoView({
                behavior: 'smooth'
            });
        });
    });

    // For chapter pages: Highlight current section in navigation
    const sectionLinks = document.querySelectorAll('.nav-tab');
    const sections = document.querySelectorAll('.content-section');
    
    if (sectionLinks.length > 0 && sections.length > 0) {
        // Add click effect to section links
        sectionLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // Remove active class from all links
                sectionLinks.forEach(l => l.classList.remove('active'));
                // Add active class to clicked link
                this.classList.add('active');
            });
        });
        
        // Update active link on scroll
        window.addEventListener('scroll', function() {
            let current = '';
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                if (pageYOffset >= sectionTop - 200) {
                    current = section.getAttribute('id');
                }
            });
            
            sectionLinks.forEach(link => {
                link.classList.remove('active');
                if (link.getAttribute('href').includes(current)) {
                    link.classList.add('active');
                }
            });
        });
    }
});

 // PWA Installation
let deferredPrompt;
const installButton = document.createElement('button');
installButton.style.display = 'none';
installButton.className = 'install-button';
installButton.textContent = 'Install AI Fluency';
installButton.setAttribute('aria-label', 'Install AI Fluency app');

document.addEventListener('DOMContentLoaded', function() {
  const headerControls = document.querySelector('.header-controls');
  if (headerControls) {
    headerControls.prepend(installButton);
  }
  
  // Special handling for mobile
  if (/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)) {
    // Add a floating install button for mobile
    const mobileInstallBtn = document.createElement('button');
    mobileInstallBtn.className = 'mobile-install-button';
    mobileInstallBtn.innerHTML = '<i class="fas fa-download"></i> Install App';
    mobileInstallBtn.style.display = 'none';
    document.body.appendChild(mobileInstallBtn);
    
    // Use the same event listener for both buttons
    mobileInstallBtn.addEventListener('click', promptInstall);
  }
});

// Wait for the beforeinstallprompt event
window.addEventListener('beforeinstallprompt', (e) => {
  // Prevent Chrome from automatically showing the prompt
  e.preventDefault();
  // Stash the event so it can be triggered later
  deferredPrompt = e;
  // Show install buttons
  installButton.style.display = 'block';
  
  // Also show mobile button if it exists
  const mobileInstallBtn = document.querySelector('.mobile-install-button');
  if (mobileInstallBtn) {
    mobileInstallBtn.style.display = 'block';
  }
  
  installButton.addEventListener('click', promptInstall);
});

// Function to handle installation
async function promptInstall() {
  if (!deferredPrompt) return;
  
  // Hide the install buttons
  installButton.style.display = 'none';
  const mobileInstallBtn = document.querySelector('.mobile-install-button');
  if (mobileInstallBtn) {
    mobileInstallBtn.style.display = 'none';
  }
  
  // Show the installation prompt
  deferredPrompt.prompt();
  
  // Wait for the user to respond to the prompt
  const { outcome } = await deferredPrompt.userChoice;
  console.log(`User response to installation: ${outcome}`);
  
  // Clear the deferred prompt variable
  deferredPrompt = null;
}

// Handle installed PWA
window.addEventListener('appinstalled', (e) => {
  console.log('AI Fluency has been installed');
  // Hide the install buttons
  installButton.style.display = 'none';
  const mobileInstallBtn = document.querySelector('.mobile-install-button');
  if (mobileInstallBtn) {
    mobileInstallBtn.style.display = 'none';
  }
});

// Generate PDF function
function generatePDF() {
    // Set up the PDF document
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('p', 'mm', 'a4');
    const pageWidth = doc.internal.pageSize.getWidth();
    const pageHeight = doc.internal.pageSize.getHeight();
    const margin = 10;
    
    // Adding title page
    doc.setFontSize(24);
    doc.setTextColor(75, 110, 251); // Primary color
    doc.text('AI Fluency', pageWidth/2, 60, { align: 'center' });
    
    doc.setFontSize(16);
    doc.setTextColor(51, 51, 51); // Text dark
    doc.text('Digital Infographic for Students', pageWidth/2, 75, { align: 'center' });
    
    doc.setFontSize(12);
    doc.text('An interactive guide to understanding Artificial Intelligence', pageWidth/2, 85, { align: 'center' });
    
    // Logo/graphic
    doc.setLineWidth(0.5);
    doc.setDrawColor(75, 110, 251);
    doc.circle(pageWidth/2, 120, 20, 'S');
    doc.setFontSize(14);
    doc.text('AI', pageWidth/2 - 5, 124);
    
    doc.setFontSize(10);
    doc.text(`Generated on ${new Date().toLocaleDateString()}`, pageWidth/2, pageHeight - 20, { align: 'center' });
    
    // Getting the current page content
    const currentPage = document.querySelector('main');
    
    if (currentPage) {
        // Capture the current page using html2canvas
        html2canvas(currentPage, { 
            scale: 2,
            logging: false,
            useCORS: true
        }).then(canvas => {
            // Add a new page
            doc.addPage();
            
            // Convert the canvas to an image and add to PDF
            const imgData = canvas.toDataURL('image/png');
            const imgWidth = pageWidth - (margin * 2);
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            
            // If the image is too tall, split it across multiple pages
            if (imgHeight > pageHeight - (margin * 2)) {
                let heightLeft = imgHeight;
                let position = 0;
                let page = 1;
                
                // While there is still content to display
                while (heightLeft > 0) {
                    // Add the image to this page
                    doc.addImage(imgData, 'PNG', margin, margin + position, imgWidth, imgHeight);
                    heightLeft -= (pageHeight - margin * 2);
                    position -= pageHeight;
                    
                    // Add a new page if needed
                    if (heightLeft > 0) {
                        doc.addPage();
                        page++;
                    }
                }
            } else {
                // If the image fits on one page
                doc.addImage(imgData, 'PNG', margin, margin, imgWidth, imgHeight);
            }
            
            // Save the PDF
            doc.save('AI_Fluency_Infographic.pdf');
        });
    } else {
        alert('Cannot generate PDF: Content not found');
    }
}