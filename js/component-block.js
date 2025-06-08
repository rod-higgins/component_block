(function ($, Drupal, drupalSettings) {
  'use strict';

  /**
   * Component Block Admin behavior
   */
  Drupal.behaviors.componentBlockAdmin = {
    attach: function (context, settings) {
      // Initialize component block overview
      $('.component-block-overview', context).once('component-block-admin').each(function() {
        initializeComponentBlockOverview($(this));
      });

      // Initialize component block preview
      $('.component-block-preview-container', context).once('component-block-preview').each(function() {
        initializeComponentBlockPreview($(this));
      });

      // Initialize component block form enhancements
      $('.component-block-field', context).once('component-block-field').each(function() {
        initializeComponentBlockField($(this));
      });

      function initializeComponentBlockOverview($overview) {
        // Add filter functionality
        var $filterInput = $('<input type="text" placeholder="Filter blocks..." class="component-block-filter">');
        $overview.before($filterInput);

        $filterInput.on('keyup', function() {
          var filterValue = $(this).val().toLowerCase();
          $overview.find('.component-block-admin-item').each(function() {
            var $row = $(this);
            var text = $row.text().toLowerCase();
            
            if (text.indexOf(filterValue) !== -1) {
              $row.show();
            } else {
              $row.hide();
            }
          });
        });

        // Add sorting functionality
        if ($.fn.tablesorter) {
          $overview.tablesorter({
            theme: 'blue',
            widgets: ['zebra', 'columns'],
            headers: {
              4: { sorter: false } // Operations column
            }
          });
        }

        // Add row highlighting
        $overview.find('.component-block-admin-item').hover(
          function() {
            $(this).addClass('component-block-item-hover');
          },
          function() {
            $(this).removeClass('component-block-item-hover');
          }
        );
      }

      function initializeComponentBlockPreview($preview) {
        // Add refresh functionality for preview
        var $refreshBtn = $('<button type="button" class="button component-block-preview-refresh">🔄 Refresh Preview</button>');
        $preview.find('.component-block-preview-actions').prepend($refreshBtn);

        $refreshBtn.on('click', function(e) {
          e.preventDefault();
          refreshComponentBlockPreview($preview);
        });

        // Add copy configuration functionality
        var $copyBtn = $('<button type="button" class="button component-block-copy-config">📋 Copy Configuration</button>');
        $preview.find('.component-block-preview-actions').append($copyBtn);

        $copyBtn.on('click', function(e) {
          e.preventDefault();
          copyComponentConfiguration($preview);
        });

        // Auto-refresh preview every 30 seconds
        setInterval(function() {
          refreshComponentBlockPreview($preview);
        }, 30000);
      }

      function initializeComponentBlockField($field) {
        // Enhance the component field with block-specific functionality
        var $refreshBtn = $field.find('.component-block-refresh-btn');
        
        $refreshBtn.on('click', function() {
          var $btn = $(this);
          var originalText = $btn.val();
          
          $btn.val('Refreshing...').prop('disabled', true);
          
          // Re-enable button after AJAX completes
          $(document).ajaxComplete(function() {
            setTimeout(function() {
              $btn.val(originalText).prop('disabled', false);
            }, 1000);
          });
        });

        // Add component selection analytics
        $field.find('select[name*="component_type"]').on('change', function() {
          var componentType = $(this).val();
          if (componentType && drupalSettings.componentBlock && drupalSettings.componentBlock.trackUsage) {
            trackComponentUsage(componentType);
          }
        });
      }

      function refreshComponentBlockPreview($preview) {
        var blockId = $preview.data('block-id');
        if (!blockId) {
          return;
        }

        // Show loading state
        var $content = $preview.find('.component-block-preview-content');
        var originalContent = $content.html();
        
        $content.html('<div class="component-block-preview-loading">🔄 Refreshing preview...</div>');

        // Make AJAX request to refresh preview
        $.ajax({
          url: '/admin/structure/block/block-content/' + blockId + '/component-preview',
          method: 'GET',
          success: function(data) {
            if (data.preview_html) {
              $content.html(data.preview_html);
            } else {
              $content.html(originalContent);
            }
          },
          error: function() {
            $content.html('<div class="component-block-preview-error">❌ Error refreshing preview</div>');
            setTimeout(function() {
              $content.html(originalContent);
            }, 3000);
          }
        });
      }

      function copyComponentConfiguration($preview) {
        var $configElement = $preview.find('pre');
        if ($configElement.length) {
          var configText = $configElement.text();
          
          // Try to copy to clipboard
          if (navigator.clipboard) {
            navigator.clipboard.writeText(configText).then(function() {
              showMessage('Configuration copied to clipboard!', 'status');
            }).catch(function() {
              showMessage('Failed to copy configuration', 'error');
            });
          } else {
            // Fallback for older browsers
            var $temp = $('<textarea>').val(configText).appendTo('body').select();
            try {
              document.execCommand('copy');
              showMessage('Configuration copied to clipboard!', 'status');
            } catch (err) {
              showMessage('Failed to copy configuration', 'error');
            }
            $temp.remove();
          }
        }
      }

      function trackComponentUsage(componentType) {
        // Send analytics data about component usage
        try {
          if (typeof gtag !== 'undefined') {
            gtag('event', 'component_selection', {
              'component_type': componentType,
              'context': 'block'
            });
          }
          
          // Also track locally for admin statistics
          var usage = JSON.parse(localStorage.getItem('componentBlockUsage') || '{}');
          usage[componentType] = (usage[componentType] || 0) + 1;
          localStorage.setItem('componentBlockUsage', JSON.stringify(usage));
        } catch (e) {
          // Ignore analytics errors
        }
      }

      function showMessage(message, type) {
        var $message = $('<div class="messages messages--' + type + '"><p>' + message + '</p></div>');
        $('body').prepend($message);
        
        setTimeout(function() {
          $message.fadeOut(function() {
            $(this).remove();
          });
        }, 3000);
      }

      // Statistics page enhancements
      $('.component-block-stats-overview', context).once('component-block-stats').each(function() {
        var $stats = $(this);
        
        // Add chart visualization if Chart.js is available
        if (typeof Chart !== 'undefined' && drupalSettings.componentBlock && drupalSettings.componentBlock.statsData) {
          createUsageChart($stats, drupalSettings.componentBlock.statsData);
        }
        
        // Add export functionality
        var $exportBtn = $('<button type="button" class="button component-block-export-stats">📊 Export Statistics</button>');
        $stats.append($exportBtn);
        
        $exportBtn.on('click', function() {
          exportStatistics();
        });
      });

      function createUsageChart($container, statsData) {
        if (!statsData.usage || Object.keys(statsData.usage).length === 0) {
          return;
        }

        var $canvas = $('<canvas id="component-usage-chart" width="400" height="200"></canvas>');
        $container.append($canvas);

        var ctx = $canvas[0].getContext('2d');
        var labels = Object.keys(statsData.usage);
        var data = Object.values(statsData.usage);

        new Chart(ctx, {
          type: 'doughnut',
          data: {
            labels: labels,
            datasets: [{
              data: data,
              backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
              ]
            }]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              title: {
                display: true,
                text: 'Component Usage Distribution'
              },
              legend: {
                position: 'bottom'
              }
            }
          }
        });
      }

      function exportStatistics() {
        // Create CSV export of statistics
        var csv = 'Component Type,Usage Count,Percentage\n';
        
        $('.component-block-stats-overview table tbody tr').each(function() {
          var $row = $(this);
          var cells = $row.find('td').map(function() {
            return $(this).text().trim();
          }).get();
          
          if (cells.length >= 3) {
            csv += cells.join(',') + '\n';
          }
        });

        // Download CSV
        var blob = new Blob([csv], { type: 'text/csv' });
        var url = window.URL.createObjectURL(blob);
        var $link = $('<a href="' + url + '" download="component-block-statistics.csv" style="display: none;">Download</a>');
        $('body').append($link);
        $link[0].click();
        $('body').remove($link);
        window.URL.revokeObjectURL(url);
      }

      // Handle version updates
      $('.component-block-update-versions', context).once('component-block-versions').each(function() {
        var $btn = $(this);
        
        $btn.on('click', function(e) {
          e.preventDefault();
          
          if (!confirm('Update all component block versions? This will refresh version hashes for all blocks.')) {
            return;
          }
          
          var originalText = $btn.text();
          $btn.text('Updating...').prop('disabled', true);
          
          $.ajax({
            url: '/admin/component-block/update-versions',
            method: 'POST',
            success: function(data) {
              if (data.status === 'success') {
                showMessage('Updated ' + data.updated_count + ' component blocks', 'status');
                setTimeout(function() {
                  location.reload();
                }, 2000);
              } else {
                showMessage('Error updating versions: ' + data.message, 'error');
              }
            },
            error: function() {
              showMessage('Error updating versions', 'error');
            },
            complete: function() {
              $btn.text(originalText).prop('disabled', false);
            }
          });
        });
      });
    }
  };

})(jQuery, Drupal, drupalSettings);